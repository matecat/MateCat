<?php


namespace Matecat\Core\Model\Teams;
use Controller\API\Commons\Exceptions\ValidationError;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\Database;
use Model\Teams\InvitedUser;
use Model\Teams\TeamDao;
use Model\Teams\TeamStruct;
use Model\Users\UserDao;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Utils\Redis\RedisHandler;
use Utils\Registry\AppConfig;
use Utils\Tools\SimpleJWT;

class InvitedUserTest extends AbstractTest
{
    private function makeValidJwt(): string
    {
        $jwt = new SimpleJWT(
            ['email' => 'invited@example.com', 'team_id' => 5],
            'simple.jwt.claims',
            AppConfig::$AUTHSECRET,
            3600
        );

        return (string)$jwt;
    }

    private function makeTeamDaoStub(): TeamDao
    {
        $stub = $this->createStub(TeamDao::class);
        $stub->method('getDatabaseHandler')->willReturn(obtainTestDatabase());
        return $stub;
    }

    private function makeUserDaoStub(): UserDao
    {
        return $this->createStub(UserDao::class);
    }

    private function makeRedisHandlerStub(array $smembersResult = []): RedisHandler
    {
        $client = new class($smembersResult) extends \Predis\Client {
            private array $smembersResult;

            public function __construct(array $smembersResult = [])
            {
                $this->smembersResult = $smembersResult;
            }

            public function __call($method, $arguments)
            {
                return match ($method) {
                    'sadd' => 1, 'expire' => true, 'srem' => 1,
                    'smembers' => $this->smembersResult,
                    default => null,
                };
            }
        };

        $handler = $this->createStub(RedisHandler::class);
        $handler->method('getConnection')->willReturn($client);

        return $handler;
    }

    #[Test]
    public function constructorParsesValidJwt(): void
    {
        $jwt = $this->makeValidJwt();
        $response = $this->createStub(\Klein\Response::class);

        $user = new InvitedUser($jwt, $response, $this->makeTeamDaoStub(), null, $this->makeUserDaoStub());

        $ref = new ReflectionProperty($user, 'jwt');
        $payload = $ref->getValue($user);

        $this->assertSame('invited@example.com', $payload['email']);
        $this->assertSame(5, $payload['team_id']);
    }

    #[Test]
    public function constructorWithEmptyJwtSkipsValidation(): void
    {
        $user = new InvitedUser('', null, $this->makeTeamDaoStub(), null, $this->makeUserDaoStub());

        $ref = new ReflectionProperty($user, 'jwt');
        $this->assertSame([], $ref->getValue($user));
    }

    #[Test]
    public function constructorThrowsValidationErrorForTamperedJwt(): void
    {
        $this->expectException(ValidationError::class);

        $response = $this->createStub(\Klein\Response::class);
        new InvitedUser(
            'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJlbWFpbCI6InRlc3RAZXhhbXBsZS5jb20ifQ.invalidsignature',
            $response,
            $this->makeTeamDaoStub(),
            null,
            $this->makeUserDaoStub()
        );
    }

    #[Test]
    public function constructorThrowsForMalformedJwt(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        $response = $this->createStub(\Klein\Response::class);
        new InvitedUser('not-a-jwt', $response, $this->makeTeamDaoStub(), null, $this->makeUserDaoStub());
    }

    #[Test]
    public function prepareUserInvitedSignUpRedirectSetsSession(): void
    {
        $jwt = $this->makeValidJwt();
        $response = $this->createStub(\Klein\Response::class);

        $_SESSION = [];
        $user = new InvitedUser($jwt, $response, $this->makeTeamDaoStub(), null, $this->makeUserDaoStub());
        $user->prepareUserInvitedSignUpRedirect();

        $this->assertArrayHasKey('invited_to_team', $_SESSION);
        $this->assertSame('invited@example.com', $_SESSION['invited_to_team']['email']);
    }

    #[Test]
    public function hasPendingInvitationsReturnsFalseWhenNoSession(): void
    {
        $_SESSION = [];
        $user = new InvitedUser(
            '',
            null,
            $this->makeTeamDaoStub(),
            $this->makeRedisHandlerStub(),
            $this->makeUserDaoStub()
        );
        $this->assertFalse($user->hasPendingInvitations());
    }

    #[Test]
    public function hasPendingInvitationsReturnsFalseWhenNoTeamId(): void
    {
        $_SESSION = ['invited_to_team' => ['email' => 'a@b.com']];
        $user = new InvitedUser(
            '',
            null,
            $this->makeTeamDaoStub(),
            $this->makeRedisHandlerStub(),
            $this->makeUserDaoStub()
        );
        $this->assertFalse($user->hasPendingInvitations());
    }

    #[Test]
    public function hasPendingInvitationsReturnsTrueWhenMembersExist(): void
    {
        $_SESSION = ['invited_to_team' => ['team_id' => 5, 'email' => 'a@b.com']];
        $user = new InvitedUser(
            '',
            null,
            $this->makeTeamDaoStub(),
            $this->makeRedisHandlerStub(['a@b.com']),
            $this->makeUserDaoStub()
        );
        $this->assertTrue($user->hasPendingInvitations());
    }

    #[Test]
    public function hasPendingInvitationsReturnsFalseWhenNoMembers(): void
    {
        $_SESSION = ['invited_to_team' => ['team_id' => 5, 'email' => 'a@b.com']];
        $user = new InvitedUser(
            '',
            null,
            $this->makeTeamDaoStub(),
            $this->makeRedisHandlerStub([]),
            $this->makeUserDaoStub()
        );
        $this->assertFalse($user->hasPendingInvitations());
    }

    #[Test]
    public function completeTeamSignUpRemovesInvitationAndClearsSession(): void
    {
        $teamStruct = new TeamStruct();
        $teamStruct->id = 5;
        $teamStruct->name = 'Test Team';
        $teamStruct->type = \Utils\Constants\Teams::GENERAL;

        $teamDao = $this->createStub(TeamDao::class);
        $teamDao->method('fetchById')->willReturn($teamStruct);
        $teamDao->method('getDatabaseHandler')->willReturn(obtainTestDatabase());

        $_SESSION = ['invited_to_team' => ['team_id' => 5, 'email' => 'member@example.com']];

        $user = new InvitedUser('', null, $teamDao, $this->makeRedisHandlerStub(), $this->makeUserDaoStub());

        $userStruct = new \Model\Users\UserStruct();
        $userStruct->uid = 1;
        $userStruct->email = 'member@example.com';

        $user->completeTeamSignUp($userStruct, ['team_id' => 5, 'email' => 'member@example.com']);

        $this->assertArrayNotHasKey('invited_to_team', $_SESSION);
    }

    #[Test]
    public function completeTeamSignUpThrowsWhenTeamNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Team not found');

        $teamDao = $this->createStub(TeamDao::class);
        $teamDao->method('fetchById')->willReturn(null);

        $user = new InvitedUser('', null, $teamDao, $this->makeRedisHandlerStub(), $this->makeUserDaoStub());

        $userStruct = new \Model\Users\UserStruct();
        $userStruct->uid = 1;

        $user->completeTeamSignUp($userStruct, ['team_id' => 999, 'email' => 'a@b.com']);
    }
}
