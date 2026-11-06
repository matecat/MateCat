<?php

namespace Matecat\Core\Controllers;

use Controller\API\V1\NewController;
use Exception;
use InvalidArgumentException;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\Engines\EngineDAO;
use Model\Engines\Structs\EngineStruct;
use Model\FeaturesBase\FeatureSet;
use Model\LQA\ModelStruct;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;
use Utils\Constants\EngineConstants;
use Utils\Engines\AbstractEngine;
use Utils\Engines\Lara;

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
        $dbProp->setValue($this->controller, obtainTestDatabase());

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
        $c->exec("DELETE FROM engines WHERE uid IN (" . (self::BASE + 20) . ", " . (self::BASE + 21) . ")");
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

    private function mtEngine(int $ownerUid, bool $builtInQualityEstimation = false): AbstractEngine
    {
        $record = EngineStruct::getStruct();
        $record->id = self::BASE + 30;
        $record->uid = $ownerUid;

        $engine = $this->createStub(AbstractEngine::class);
        $engine->method('getEngineRecord')->willReturn($record);
        $engine->method('hasBuiltInQualityEstimation')->willReturn($builtInQualityEstimation);

        return $engine;
    }

    private function seedLaraEngine(int $uid): void
    {
        $engine = new EngineStruct();
        $engine->name = 'ctrltest lara engine';
        $engine->type = EngineConstants::MT;
        $engine->description = 'ctrltest lara engine';
        $engine->base_url = 'https://example.invalid/api';
        $engine->translate_relative_url = 'get';
        $engine->others = [];
        $engine->extra_parameters = ['Lara-AccessKeyId' => 'kid', 'Lara-AccessKeySecret' => 'ksecret'];
        $engine->class_load = Lara::class;
        $engine->penalty = 14;
        $engine->active = true;
        $engine->uid = $uid;

        (new EngineDAO(obtainTestDatabase()))->create($engine);
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
        $this->expectExceptionMessage('This QA model does not belong to the authenticated user');
        $this->invokeMethod('validateQaModel', [(string)$this->qaModelId(self::BASE)]);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateQaModel_missing_model_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This QA model does not exist');
        // No qa_model seeded for this id.
        $this->invokeMethod('validateQaModel', [(string)$this->qaModelId(self::BASE)]);
    }

    // ──────────────── validateQaModelTemplate() ────────────────

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateQaModelTemplate_empty_value_returns_null(): void
    {
        $this->assertNull($this->invokeMethod('validateQaModelTemplate', [null]));
        $this->assertNull($this->invokeMethod('validateQaModelTemplate', [false]));
        $this->assertNull($this->invokeMethod('validateQaModelTemplate', ['']));
    }

    /**
     * The lookup is scoped by uid, so an id with no row for this user is indistinguishable from a
     * template belonging to somebody else — one message covers both.
     *
     * @throws ReflectionException
     */
    #[Test]
    public function validateQaModelTemplate_unknown_template_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This QA model template does not exist or does not belong to the logged-in user');
        // No qa_model_templates row is seeded for this reserved id.
        $this->invokeMethod('validateQaModelTemplate', [(string)(self::BASE + 8)]);
    }

    // ──────────────── validateMtEvaluationLicense() ────────────────

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateMtEvaluationLicense_refuses_an_engine_without_its_own_score_when_the_owner_has_no_lara_engine(): void
    {
        $engine = $this->mtEngine(self::BASE + 20);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MT evaluation requires an active Lara license');
        $this->expectExceptionCode(-2);

        $this->invokeMethod('validateMtEvaluationLicense', [$engine]);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateMtEvaluationLicense_accepts_an_engine_when_the_owner_has_an_active_lara_engine(): void
    {
        $this->seedLaraEngine(self::BASE + 21);
        $engine = $this->mtEngine(self::BASE + 21);

        $this->assertNull($this->invokeMethod('validateMtEvaluationLicense', [$engine]));
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateMtEvaluationLicense_accepts_an_engine_with_its_own_score_without_a_lara_engine(): void
    {
        $engine = $this->mtEngine(self::BASE + 20, builtInQualityEstimation: true);

        $this->assertNull($this->invokeMethod('validateMtEvaluationLicense', [$engine]));
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateMtEvaluationLicense_accepts_a_lara_engine_without_looking_up_the_owner(): void
    {
        $engine = $this->createMock(Lara::class);
        $engine->expects($this->never())->method('getEngineRecord');
        $engine->method('hasBuiltInQualityEstimation')->willReturn(false);

        $this->assertNull($this->invokeMethod('validateMtEvaluationLicense', [$engine]));
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function validateMtEvaluationLicense_accepts_no_mt_engine(): void
    {
        $this->assertNull($this->invokeMethod('validateMtEvaluationLicense', [null]));
    }
}
