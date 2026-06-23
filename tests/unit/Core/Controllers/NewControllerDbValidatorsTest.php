<?php

namespace Matecat\Core\Controllers;

use Controller\API\V1\NewController;
use Exception;
use InvalidArgumentException;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\LQA\ModelStruct;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;

/**
 * Real-DB driven coverage for the DAO-backed validator helpers of
 * {@see NewController} (validateTeam / validateQaModel) that the
 * pure-unit suites cannot reach.
 *
 * Reserved ID block (Playbook §4): base = 9_026_000.
 *   base+5 team, base+6 user/uid, base+7 qa_model, base+12 teams_users.
 * Per-suite owner email: ctrltest_9026000@example.org.
 * Cleans ONLY by reserved id; parent::tearDown() is the mandatory last line.
 */
#[Group('PersistenceNeeded')]
class NewControllerDbValidatorsTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_026_000;

    private NewController $controller;
    /** @var ReflectionClass<NewController> */
    private ReflectionClass $reflector;
    private UserStruct $user;

    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->cleanDb();

        $this->seedUser(self::BASE);
        $this->seedTeam(self::BASE);
        $this->seedMembership(self::BASE);

        $this->reflector = new ReflectionClass(NewController::class);
        $this->controller = $this->reflector->newInstanceWithoutConstructor();

        $this->user = new UserStruct();
        $this->user->uid = $this->userId(self::BASE);
        $this->user->email = $this->ownerEmail(self::BASE);
        $userProp = $this->reflector->getProperty('user');
        $userProp->setValue($this->controller, $this->user);

        $dbProp = $this->reflector->getProperty('database');
        $dbProp->setValue($this->controller, Database::obtain());

        $fsProp = $this->reflector->getProperty('featureSet');
        $fsProp->setValue($this->controller, new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
    }

    public function tearDown(): void
    {
        $this->cleanDb();
        parent::tearDown();
    }

    private function cleanDb(): void
    {
        $c = $this->seedConnection();
        $c->exec("DELETE FROM qa_models WHERE id = " . $this->qaModelId(self::BASE));
        $c->exec("DELETE FROM teams_users WHERE id = " . $this->teamUserId(self::BASE));
        $c->exec("DELETE FROM teams WHERE id = " . $this->teamId(self::BASE));
        $c->exec("DELETE FROM users WHERE uid = " . $this->userId(self::BASE));
    }

    /**
     * Seed a qa_model row including the NOT-NULL `hash` column that the
     * shared seedQaModel() fragment omits (ModelStruct::$hash is non-nullable).
     */
    private function seedQaModelWithHash(string $label): void
    {
        $id  = $this->qaModelId(self::BASE);
        $uid = $this->userId(self::BASE);
        $this->seedConnection()->exec(
            "INSERT IGNORE INTO qa_models (id, uid, create_date, label, pass_type, pass_options, hash) "
            . "VALUES ($id, $uid, NOW(), '$label', 'standard', '{}', 'ctrltest_hash_" . self::BASE . "')"
        );
    }

    /**
     * @param array<int, mixed> $args
     *
     * @throws ReflectionException
     */
    private function invokeMethod(string $name, array $args = []): mixed
    {
        return $this->reflector->getMethod($name)->invokeArgs($this->controller, $args);
    }

    // ──────────────── validateTeam() ────────────────

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTeam_with_seeded_membership_returns_team(): void
    {
        $team = $this->invokeMethod('validateTeam', [(string)$this->teamId(self::BASE)]);

        $this->assertInstanceOf(TeamStruct::class, $team);
        $this->assertSame($this->teamId(self::BASE), (int)$team->id);
        $this->assertSame('CtrlTestTeam_' . self::BASE, $team->name);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateTeam_unmatched_membership_throws(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Team and user membership does not match');
        // A team id outside the seeded block has no membership for this user.
        $this->invokeMethod('validateTeam', [(string)(self::BASE + 999)]);
    }

    // ──────────────── validateQaModel() ────────────────

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateQaModel_default_label_returns_model(): void
    {
        $this->seedQaModelWithHash('default');

        $model = $this->invokeMethod('validateQaModel', [(string)$this->qaModelId(self::BASE)]);

        $this->assertInstanceOf(ModelStruct::class, $model);
        $this->assertSame($this->qaModelId(self::BASE), (int)$model->id);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateQaModel_unknown_label_not_in_featureset_throws(): void
    {
        $this->seedQaModelWithHash('CtrlTestModel');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This QA Model does not belong to the authenticated user');
        $this->invokeMethod('validateQaModel', [(string)$this->qaModelId(self::BASE)]);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateQaModel_missing_model_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This QA Model does not exists');
        // No qa_model seeded for this id.
        $this->invokeMethod('validateQaModel', [(string)$this->qaModelId(self::BASE)]);
    }
}
