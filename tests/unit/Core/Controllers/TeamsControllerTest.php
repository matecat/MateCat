<?php

/**
 * Real-DB controller test for {@see \Controller\API\V2\TeamsController}.
 *
 * ID block base = 9031000 (controller-coverage plan, Wave 5 N=31).
 * Reserved ids inside the block: base+1 project, base+2 job, base+3 segment,
 * base+4 file, base+5 team, base+6 user/uid, ... (see ControllerSeedFragments).
 * Clean ONLY by reserved id; per-suite owner email = ctrltest_9031000@example.org.
 *
 * NOTE: most happy paths use a freshly-created factory user (which owns its own
 * personal team) so the team-creation / membership machinery runs against real
 * rows. Those factory rows are cleaned up by uid in tearDown().
 */

namespace Matecat\Core\Controllers;

use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\TeamAccessValidator;
use Controller\API\V2\TeamsController;
use Exception;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Matecat\TestHelpers\Factory\User;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\Teams\TeamDao;
use Model\Users\UserStruct;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Event\NoPreviousThrowableException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Exception as PHPUnitException;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\GeneratorNotSupportedException;
use PHPUnit\Framework\InvalidArgumentException as PHPUnitInvalidArgumentException;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Throwable;
use TypeError;
use Utils\Constants\Teams;
use Utils\Logger\MatecatLogger;

class TestableTeamsController extends TeamsController
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }

    protected function registerValidators(): void
    {
    }
}

#[AllowMockObjectsWithoutExpectations]
class TeamsControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_031_000;

    /** @var ReflectionClass<TeamsController> */
    private ReflectionClass $reflector;
    private TestableTeamsController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;
    private UserStruct $user;

    /** @var list<int> uids created via the User factory, cleaned in tearDown */
    private array $factoryUids = [];

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     * @throws NoPreviousThrowableException
     * @throws PHPUnitInvalidArgumentException
     * @throws MockObjectException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);

        $this->controller = new TestableTeamsController();
        $this->reflector = new ReflectionClass(TeamsController::class);

        $this->requestStub = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);
        $this->setProp('database', obtainTestDatabase());

        $this->user = new UserStruct();
        $this->user->uid = $this->userId(self::BASE);
        $this->user->email = $this->ownerEmail(self::BASE);
        $this->user->first_name = 'Ctrl';
        $this->user->last_name = 'Tester';
        $this->setProp('user', $this->user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
        // non-empty api_key short-circuits refreshClientSessionIfNotApi() so no
        // PHP session machinery runs during action invocation.
        $this->setProp('api_key', 'ctrltestapikey');
    }

    /**
     * @throws PDOException
     */
    protected function tearDown(): void
    {
        $this->cleanFragments(self::BASE);

        foreach ($this->factoryUids as $uid) {
            $this->cleanupUserTeams($uid);
        }

        parent::tearDown();
    }

    /**
     * @throws PDOException
     */
    private function cleanupUserTeams(int $uid): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $stmt = $conn->query("SELECT id FROM teams WHERE created_by = $uid");
        $teamIds = $stmt instanceof PDOStatement ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        foreach ($teamIds as $teamId) {
            $conn->exec("DELETE FROM teams_users WHERE id_team = $teamId");
            $conn->exec("DELETE FROM teams WHERE id = $teamId");
        }
        $conn->exec("DELETE FROM users WHERE uid = $uid");
    }

    /**
     * @throws ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $prop = $this->reflector->getProperty($name);
        $prop->setValue($this->controller, $value);
    }

    /**
     * @param array<string, mixed> $params
     *
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams = ['REQUEST_URI' => '/api/v2/teams', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub = new Request($params, $params, [], $serverParams);
        $this->setProp('request', $this->requestStub);
        // KleinController->update() reads $this->params (merged), so keep in sync.
        $this->setProp('params', $params);
    }

    /**
     * Create a real user (with its own personal team) and inject it as the
     * acting user. The user factory makes a fresh row each call.
     *
     * @throws ReflectionException
     * @throws TypeError
     * @throws Exception
     */
    private function actAsFactoryUser(): UserStruct
    {
        $user = User::create();
        $this->factoryUids[] = $user->uid;
        $this->setProp('user', $user);
        $this->user = $user;

        return $user;
    }

    // ─── registerValidators / addValidatorAccess ───

    /**
     * @throws ReflectionException
     * @throws PHPUnitException
     */
    #[Test]
    public function registerValidators_appends_login_validator(): void
    {
        $real = new ReflectionClass(TeamsController::class);
        $m = $real->getMethod('registerValidators');
        $m->invoke($this->controller);

        $validators = $real->getProperty('validators')->getValue($this->controller);
        $this->assertCount(1, $validators);
        $this->assertInstanceOf(LoginValidator::class, $validators[0]);
    }

    /**
     * @throws ReflectionException
     * @throws PHPUnitException
     */
    #[Test]
    public function addValidatorAccess_appends_team_access_validator(): void
    {
        $real = new ReflectionClass(TeamsController::class);
        $m = $real->getMethod('addValidatorAccess');
        $m->invoke($this->controller);

        $validators = $real->getProperty('validators')->getValue($this->controller);
        $this->assertCount(1, $validators);
        $this->assertInstanceOf(TeamAccessValidator::class, $validators[0]);
    }

    // ─── create() failure paths ───

    /**
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function create_throws_when_name_is_empty(): void
    {
        $this->setRequestParams(['name' => '   ', 'type' => Teams::GENERAL]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('name is empty');

        $this->controller->create();
    }

    /**
     * The team name is quoted back in the invitation email MateCat sends to any address
     * the owner supplies, and mail clients auto-link anything that looks like a URL. A
     * name that reads as a link therefore turns that email into a carrier for someone
     * else's destination, so link-shaped names are refused. Reported externally as
     * "Hyperlink Injection in Team name".
     *
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    #[DataProvider('linkShapedNames')]
    public function create_throws_when_name_looks_like_a_link(string $name): void
    {
        $this->setRequestParams(['name' => $name, 'type' => Teams::GENERAL]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('name cannot contain a URL or a domain name');

        $this->controller->create();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function linkShapedNames(): array
    {
        return [
            'the reported payload' => ['https://bing.com'],
            'scheme only' => ['http://x'],
            'javascript scheme' => ['javascript://comment'],
            'www prefix' => ['www.example.com'],
            'bare hostname' => ['example.com'],
            'hostname inside a pretext' => ['Verify your account at evil-login.co now'],
            'uppercase' => ['HTTPS://EVIL.COM'],
            'subdomain' => ['login.microsoftonline.com'],
            // The email templates escape with double_encode: false, so entity text reaches the
            // recipient and their mail client turns it back into a dot. The rule has to be
            // applied to what the reader sees, not to what was typed.
            'dot smuggled as a decimal entity' => ['evil&#46;com'],
            'dot smuggled as a hex entity' => ['evil&#x2E;com'],
            'dot smuggled as a named entity' => ['evil&period;com'],
            'scheme smuggled as an entity' => ['https&#58;//evil.com'],
        ];
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function create_throws_when_name_is_longer_than_the_maximum(): void
    {
        $this->setRequestParams(['name' => str_repeat('a', 101), 'type' => Teams::GENERAL]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('name must be at most 100 characters');

        $this->controller->create();
    }

    /**
     * @throws Exception
     * @throws TypeError
     * @throws PDOException
     */
    #[Test]
    public function create_measures_the_cap_against_the_decoded_name(): void
    {
        $this->actAsFactoryUser();
        // fifty visible characters written with entities: 250 raw, which the raw cap rejected
        $this->setRequestParams(['name' => str_repeat('&amp;', 50), 'type' => Teams::GENERAL]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->create();

        $this->assertIsArray($captured);
        $this->assertSame(str_repeat('&amp;', 50), $captured['team']['name']);
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function create_throws_when_the_stored_name_is_wider_than_the_column(): void
    {
        // decodes to 52 visible characters, but 260 raw ones do not fit a varchar(255)
        $this->setRequestParams(['name' => str_repeat('&amp;', 52), 'type' => Teams::GENERAL]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('name must be at most 255 characters');

        $this->controller->create();
    }

    /**
     * The guard must not reject the ordinary names people actually use: it stops at the
     * next check (type) rather than complaining about the name.
     *
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    #[DataProvider('acceptableNames')]
    public function create_accepts_a_plain_team_name(string $name): void
    {
        $this->setRequestParams(['name' => $name, 'type' => '']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('type is empty');

        $this->controller->create();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function acceptableNames(): array
    {
        return [
            'plain' => ['Marketing Team'],
            'apostrophe' => ["O'Brien & Sons"],
            'version number' => ['Localisation 2.0'],
            'abbreviation with single letters' => ['Acme S.r.l.'],
            'non ascii' => ['Équipe Française'],
            'exactly at the limit' => [str_repeat('a', 100)],
        ];
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function create_throws_when_type_is_empty(): void
    {
        $this->setRequestParams(['name' => 'My Team', 'type' => '']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('type is empty');

        $this->controller->create();
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    #[Test]
    public function create_throws_when_type_is_not_allowed(): void
    {
        $this->setRequestParams(['name' => 'My Team', 'type' => 'bogus']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('type is not allowed');

        $this->controller->create();
    }

    // ─── create() happy path ───

    /**
     * @throws Exception
     * @throws TypeError
     * @throws PHPUnitException
     * @throws ExpectationFailedException
     */
    #[Test]
    public function create_returns_team_payload_with_seeded_fields(): void
    {
        $user = $this->actAsFactoryUser();
        $this->setRequestParams(['name' => 'Ctrl General Team', 'type' => Teams::GENERAL]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->create();

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('team', $captured);
        $this->assertSame('Ctrl General Team', $captured['team']['name']);
        $this->assertSame(Teams::GENERAL, $captured['team']['type']);
        $this->assertSame($user->uid, $captured['team']['created_by']);
        $this->assertGreaterThan(0, $captured['team']['id']);
    }

    /**
     * Names are stored as typed. Encoding them on the way in put entity text in the column,
     * which neither a JSON response nor a JavaScript string can decode back, so a team called
     * "A & B" displayed as "A &amp; B" everywhere. Each output escapes instead.
     *
     * @throws Exception
     * @throws TypeError
     * @throws PDOException
     */
    #[Test]
    #[DataProvider('namesWithHtmlSignificantCharacters')]
    public function create_stores_the_name_exactly_as_typed(string $typed): void
    {
        $this->actAsFactoryUser();
        $this->setRequestParams(['name' => $typed, 'type' => Teams::GENERAL]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->create();

        $this->assertIsArray($captured);
        $this->assertSame($typed, $captured['team']['name'], 'the payload must return the name as typed');

        $stmt = obtainTestDatabase()->getConnection()->query(
            "SELECT name FROM teams WHERE id = " . (int)$captured['team']['id']
        );
        $stored = $stmt instanceof PDOStatement ? $stmt->fetchColumn() : null;
        $this->assertSame($typed, $stored, 'the column must hold the name as typed, not entity text');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function namesWithHtmlSignificantCharacters(): array
    {
        return [
            'ampersand' => ['A & B'],
            'apostrophe' => ["O'Brien Team"],
            'double quote' => ['The "Best" Team'],
            'angle brackets' => ['Team <core>'],
            'entity-looking text typed literally' => ['A &amp; B'],
            'non ascii' => ['Équipe Française'],
        ];
    }

    /**
     * Store-time encoding used to remove control characters as a side effect. It is now done
     * on purpose: a name is one line of text, and CR/LF would otherwise reach the Subject
     * header of the membership emails.
     *
     * @throws Exception
     * @throws TypeError
     * @throws PDOException
     */
    #[Test]
    public function create_strips_control_characters_and_collapses_whitespace(): void
    {
        $this->actAsFactoryUser();
        $this->setRequestParams([
            'name' => "Team\r\nBcc: victim\tand\x00more   spaces",
            'type' => Teams::GENERAL,
        ]);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->create();

        $this->assertIsArray($captured);
        $stored = $captured['team']['name'];
        $this->assertIsString($stored);
        $this->assertSame('Team Bcc: victim and more spaces', $stored);
        $this->assertStringNotContainsString("\r", $stored);
        $this->assertStringNotContainsString("\n", $stored);
    }

    // ─── getTeamList() happy path ───

    /**
     * @throws Exception
     * @throws TypeError
     * @throws PHPUnitException
     * @throws ExpectationFailedException
     * @throws GeneratorNotSupportedException
     */
    #[Test]
    public function getTeamList_returns_teams_payload_for_user(): void
    {
        $this->actAsFactoryUser(); // owns a personal team

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->getTeamList();

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('teams', $captured);
        $this->assertNotEmpty($captured['teams']);
        $this->assertArrayHasKey('id', $captured['teams'][0]);
        $this->assertArrayHasKey('name', $captured['teams'][0]);
    }

    // ─── update() failure paths ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function update_throws_authorization_error_when_user_not_member(): void
    {
        // user has no membership to id_team => TeamAccessValidator rejects.
        $this->setRequestParams(['id_team' => '8888881', 'name' => 'New Name']);

        $this->expectException(AuthorizationError::class);
        $this->expectExceptionCode(401);

        $this->controller->update();
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function update_throws_when_name_is_empty(): void
    {
        $user = $this->actAsFactoryUser();
        $team = (new TeamDao(obtainTestDatabase()))->createUserTeam($user, [
            'type' => Teams::GENERAL,
            'name' => 'Updatable Team',
        ]);

        $this->setRequestParams(['id_team' => (string)$team->id, 'name' => '   ']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('name is empty');

        $this->controller->update();
    }

    /**
     * Renaming has to be guarded as well as creating: the invitation email quotes whatever
     * the name is at send time, so a team created with a plain name could otherwise be
     * renamed to a link before members are added.
     *
     * @throws Exception
     * @throws TypeError
     * @throws ReflectionException
     */
    #[Test]
    public function update_throws_when_name_looks_like_a_link(): void
    {
        $user = $this->actAsFactoryUser();
        $team = (new TeamDao(obtainTestDatabase()))->createUserTeam($user, [
            'type' => Teams::GENERAL,
            'name' => 'Updatable Team',
        ]);

        $this->setRequestParams(['id_team' => (string)$team->id, 'name' => 'https://bing.com']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(400);
        $this->expectExceptionMessage('name cannot contain a URL or a domain name');

        $this->controller->update();
    }

    /**
     * The rename is refused before it reaches the database, so the stored name is intact.
     *
     * @throws Exception
     * @throws TypeError
     * @throws ReflectionException
     * @throws PDOException
     */
    #[Test]
    public function update_leaves_the_stored_name_untouched_when_it_looks_like_a_link(): void
    {
        $user = $this->actAsFactoryUser();
        $team = (new TeamDao(obtainTestDatabase()))->createUserTeam($user, [
            'type' => Teams::GENERAL,
            'name' => 'Untouched Team',
        ]);

        $this->setRequestParams(['id_team' => (string)$team->id, 'name' => 'evil.com']);

        try {
            $this->controller->update();
            $this->fail('expected the link-shaped name to be refused');
        } catch (InvalidArgumentException) {
            // expected
        }

        $stmt = obtainTestDatabase()->getConnection()->query("SELECT name FROM teams WHERE id = " . (int)$team->id);
        $stored = $stmt instanceof PDOStatement ? $stmt->fetchColumn() : null;
        $this->assertSame('Untouched Team', $stored);
    }

    // ─── update() happy path ───

    /**
     * @throws Throwable
     * @throws PHPUnitException
     * @throws ExpectationFailedException
     * @throws GeneratorNotSupportedException
     */
    #[Test]
    public function update_returns_renamed_team_payload(): void
    {
        $user = $this->actAsFactoryUser();
        $team = (new TeamDao(obtainTestDatabase()))->createUserTeam($user, [
            'type' => Teams::GENERAL,
            'name' => 'Old Team Name',
        ]);

        $this->setRequestParams(['id_team' => (string)$team->id, 'name' => 'Renamed Team']);

        $captured = null;
        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data) use (&$captured): bool {
                $captured = $data;
                return true;
            }));

        $this->controller->update();

        $this->assertIsArray($captured);
        $this->assertArrayHasKey('team', $captured);
        $this->assertNotEmpty($captured['team']);
        $this->assertSame('Renamed Team', $captured['team'][0]['name']);
        $this->assertSame($team->id, $captured['team'][0]['id']);
    }
}
