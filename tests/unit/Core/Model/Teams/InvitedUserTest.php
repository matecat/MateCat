<?php


namespace Matecat\Core\Model\Teams;

use Utils\Session\ArraySessionStore;
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
                    // From the same seed, so a test cannot arrange a set and a membership answer
                    // that contradict each other.
                    'sismember' => in_array($arguments[1], $this->smembersResult, true) ? 1 : 0,
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

        $user = new InvitedUser($jwt, $response, $this->makeTeamDaoStub(), null, $this->makeUserDaoStub(), new ArraySessionStore());

        $ref = new ReflectionProperty($user, 'jwt');
        $payload = $ref->getValue($user);

        $this->assertSame('invited@example.com', $payload['email']);
        $this->assertSame(5, $payload['team_id']);
    }

    #[Test]
    public function constructorWithEmptyJwtSkipsValidation(): void
    {
        $user = new InvitedUser('', null, $this->makeTeamDaoStub(), null, $this->makeUserDaoStub(), new ArraySessionStore());

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
            $this->makeUserDaoStub(),
            new ArraySessionStore()
        );
    }

    #[Test]
    public function constructorThrowsForMalformedJwt(): void
    {
        $this->expectException(\UnexpectedValueException::class);

        $response = $this->createStub(\Klein\Response::class);
        new InvitedUser('not-a-jwt', $response, $this->makeTeamDaoStub(), null, $this->makeUserDaoStub(), new ArraySessionStore());
    }

    #[Test]
    public function prepareUserInvitedSignUpRedirectSetsSession(): void
    {
        $jwt = $this->makeValidJwt();
        $response = $this->createStub(\Klein\Response::class);

        $session = new ArraySessionStore();
        $user = new InvitedUser($jwt, $response, $this->makeTeamDaoStub(), null, $this->makeUserDaoStub(), $session);
        $user->prepareUserInvitedSignUpRedirect();

        $this->assertTrue($session->has('invited_to_team'));
        $this->assertSame('invited@example.com', $session->get('invited_to_team')['email']);
    }

    #[Test]
    public function hasPendingInvitationsReturnsFalseWhenNoSession(): void
    {
        $user = new InvitedUser(
            '',
            null,
            $this->makeTeamDaoStub(),
            $this->makeRedisHandlerStub(),
            $this->makeUserDaoStub(),
            new ArraySessionStore()
        );
        $this->assertFalse($user->hasPendingInvitations());
    }

    /**
     * The invitation is present but carries no team_id, which is the condition under test. It has to
     * be seeded into the injected store: this case used to write it to $_SESSION while handing the
     * subject an empty ArraySessionStore, so it never got past the no-session check above and was
     * really a duplicate of it.
     */
    #[Test]
    public function hasPendingInvitationsReturnsFalseWhenNoTeamId(): void
    {
        $user = new InvitedUser(
            '',
            null,
            $this->makeTeamDaoStub(),
            $this->makeRedisHandlerStub(),
            $this->makeUserDaoStub(),
            new ArraySessionStore(['invited_to_team' => ['email' => 'a@b.com']])
        );
        $this->assertFalse($user->hasPendingInvitations());
    }

    #[Test]
    public function hasPendingInvitationsReturnsTrueWhenMembersExist(): void
    {
        $user = new InvitedUser(
            '',
            null,
            $this->makeTeamDaoStub(),
            $this->makeRedisHandlerStub(['a@b.com']),
            $this->makeUserDaoStub(),
            new ArraySessionStore(['invited_to_team' => ['team_id' => 5, 'email' => 'a@b.com']])
        );
        $this->assertTrue($user->hasPendingInvitations());
    }

    /**
     * A complete invitation whose team has no pending members — so the false comes from the empty
     * member list, not from a missing session. Same fix as the no-team_id case: with the data in
     * $_SESSION and an empty store, the member lookup was never reached and makeRedisHandlerStub([])
     * was never the reason this returned false.
     */
    #[Test]
    public function hasPendingInvitationsReturnsFalseWhenNoMembers(): void
    {
        $user = new InvitedUser(
            '',
            null,
            $this->makeTeamDaoStub(),
            $this->makeRedisHandlerStub([]),
            $this->makeUserDaoStub(),
            new ArraySessionStore(['invited_to_team' => ['team_id' => 5, 'email' => 'a@b.com']])
        );
        $this->assertFalse($user->hasPendingInvitations());
    }

    /**
     * The withdrawn invitation. Somebody else's invitation to the same team is still open, so the
     * team's set is not empty — which used to be the whole test, and meant removing this address from
     * the set did nothing: their signup link kept working for as long as any other invitation stood.
     */
    #[Test]
    public function hasPendingInvitationsIsFalseWhenOnlyAnotherAddressIsStillInvited(): void
    {
        $user = new InvitedUser(
            '',
            null,
            $this->makeTeamDaoStub(),
            $this->makeRedisHandlerStub(['someone.else@example.com']),
            $this->makeUserDaoStub(),
            new ArraySessionStore(['invited_to_team' => ['team_id' => 5, 'email' => 'a@b.com']])
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

        $session = new ArraySessionStore(['invited_to_team' => ['team_id' => 5, 'email' => 'member@example.com']]);

        $user = new InvitedUser('', null, $teamDao, $this->makeRedisHandlerStub(), $this->makeUserDaoStub(), $session);

        $userStruct = new \Model\Users\UserStruct();
        $userStruct->uid = 1;
        $userStruct->email = 'member@example.com';

        $user->completeTeamSignUp($userStruct);

        $this->assertFalse($session->has('invited_to_team'));
    }

    #[Test]
    public function completeTeamSignUpThrowsWhenTeamNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Team not found');

        $teamDao = $this->createStub(TeamDao::class);
        $teamDao->method('fetchById')->willReturn(null);

        $session = new ArraySessionStore(['invited_to_team' => ['team_id' => 999, 'email' => 'a@b.com']]);
        $user = new InvitedUser('', null, $teamDao, $this->makeRedisHandlerStub(), $this->makeUserDaoStub(), $session);

        $userStruct = new \Model\Users\UserStruct();
        $userStruct->uid = 1;

        $user->completeTeamSignUp($userStruct);
    }
}
