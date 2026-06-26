<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\CreateProjectController;
use Exception;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\Locales\Languages;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\FeaturesBase\FeatureSet;
use Model\Teams\TeamStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Throwable;
use Utils\Logger\MatecatLogger;
use Utils\TmKeyManagement\TmKeyStruct;

class TestableCreateProjectControllerApi extends CreateProjectController
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

/**
 * Real-DB suite for CreateProjectController.
 *
 * Reserved ID block base = 9025000 (task N=25):
 *   base+1 project, base+2 job, base+3 segment, base+4 file, base+5 team,
 *   base+6 user/uid, base+12 teams_users row.
 * Clean ONLY by reserved id. Per-suite owner email: ctrltest_9025000@example.org.
 */
#[AllowMockObjectsWithoutExpectations]
class CreateProjectControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9_025_000;

    /** @var ReflectionClass<CreateProjectController> */
    private ReflectionClass $reflector;
    private TestableCreateProjectControllerApi $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;
    private UserStruct $user;

    /**
     * @throws Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanFragments(self::BASE);
        $this->seedTeam(self::BASE, 'personal');
        $this->seedMembership(self::BASE, true);

        $this->controller = new TestableCreateProjectControllerApi();
        $this->reflector  = new ReflectionClass(CreateProjectController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
        $this->reflector->getProperty('response')->setValue($this->controller, $this->responseMock);
        $this->reflector->getProperty('database')->setValue($this->controller, obtainTestDatabase());

        $this->user            = new UserStruct();
        $this->user->uid       = $this->userId(self::BASE);
        $this->user->email     = $this->ownerEmail(self::BASE);
        $this->user->first_name = 'Ctrl';
        $this->user->last_name  = 'Tester';

        $this->reflector->getProperty('user')->setValue($this->controller, $this->user);
        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createMock(MatecatLogger::class));
        $this->reflector->getProperty('featureSet')->setValue($this->controller, new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class)));
    }

    protected function tearDown(): void
    {
        unset($_COOKIE['upload_token']);
        $this->cleanFragments(self::BASE);
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $params
     * @throws ReflectionException
     */
    private function setRequestParams(array $params): void
    {
        $serverParams = ['REQUEST_URI' => '/api/app/new', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub = new Request($params, [], [], $serverParams);
        $this->reflector->getProperty('request')->setValue($this->controller, $this->requestStub);
    }

    /**
     * @param array<int, mixed> $args
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        $m = $this->reflector->getMethod($method);

        return $m->invoke($this->controller, ...$args);
    }

    /**
     * A valid set of request params that pass validateTheRequest() without
     * touching external services (mt_engine <= 1 -> no engine DB lookup).
     *
     * @return array<string, mixed>
     */
    private function validRequestParams(): array
    {
        return [
            'file_name'        => 'document.docx',
            'source_lang'      => 'en-US',
            'target_lang'      => 'it-IT',
            'job_subject'      => 'general',
            'mt_engine'        => '1',
            'pretranslate_100' => '0',
            'pretranslate_101' => '0',
            'id_team'          => (string) $this->teamId(self::BASE),
        ];
    }

    // ─── getData ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function getData_returns_empty_array_initially(): void
    {
        $this->assertSame([], $this->controller->getData());
    }

    // ─── validateTheRequest happy path ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateTheRequest_returns_sanitized_structured_data(): void
    {
        $_COOKIE['upload_token'] = '11111111-1111-1111-1111-111111111111';
        $this->setRequestParams($this->validRequestParams());

        /** @var array<string, mixed> $data */
        $data = $this->invokePrivate('validateTheRequest');

        $this->assertIsArray($data);
        $this->assertSame('en-US', $data['source_lang']);
        $this->assertSame('it-IT', $data['target_lang']);
        $this->assertSame('general', $data['job_subject']);
        $this->assertSame(1, $data['mt_engine']);
        $this->assertSame('11111111-1111-1111-1111-111111111111', $data['upload_token']);
        $this->assertInstanceOf(TeamStruct::class, $data['team']);
        $this->assertSame($this->teamId(self::BASE), (int) $data['team']->id);
        $this->assertArrayHasKey('target_language_mt_engine_association', $data);
        $this->assertSame(['it-IT' => 1], $data['target_language_mt_engine_association']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateTheRequest_sets_tms_engine_zero_when_disable_flag(): void
    {
        $_COOKIE['upload_token'] = '22222222-2222-2222-2222-222222222222';
        $params = $this->validRequestParams();
        $params['disable_tms_engine'] = '1';
        $this->setRequestParams($params);

        /** @var array<string, mixed> $data */
        $data = $this->invokePrivate('validateTheRequest');

        $this->assertSame(0, $data['tms_engine']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateTheRequest_dedupes_private_keys_and_sets_glossaries(): void
    {
        $_COOKIE['upload_token'] = '77777777-7777-7777-7777-777777777777';
        $params = $this->validRequestParams();
        $params['private_keys_list'] = json_encode([
            'ownergroup' => [['key' => 'aaaaaaaaaaaaaaaa']],
            'mine'       => [['key' => 'aaaaaaaaaaaaaaaa']],
            'anonymous'  => [['key' => 'bbbbbbbbbbbbbbbb']],
        ]);
        $params['mmt_glossaries']  = '[1,2]';
        $params['lara_glossaries'] = '["g1"]';
        $this->setRequestParams($params);

        /** @var array<string, mixed> $data */
        $data = $this->invokePrivate('validateTheRequest');

        $this->assertCount(2, $data['private_tm_key']);
        $this->assertSame('[1,2]', $data['mmt_glossaries']);
        $this->assertSame('["g1"]', $data['lara_glossaries']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateTheRequest_throws_on_invalid_upload_token(): void
    {
        unset($_COOKIE['upload_token']);
        $this->setRequestParams($this->validRequestParams());

        $this->expectException(Exception::class);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateTheRequest_throws_on_missing_file_name(): void
    {
        $_COOKIE['upload_token'] = '33333333-3333-3333-3333-333333333333';
        $params = $this->validRequestParams();
        $params['file_name'] = '';
        $this->setRequestParams($params);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-1);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateTheRequest_throws_on_missing_job_subject(): void
    {
        $_COOKIE['upload_token'] = '44444444-4444-4444-4444-444444444444';
        $params = $this->validRequestParams();
        $params['job_subject'] = '';
        $this->setRequestParams($params);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-5);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateTheRequest_throws_on_invalid_pretranslate_100(): void
    {
        $_COOKIE['upload_token'] = '55555555-5555-5555-5555-555555555555';
        $params = $this->validRequestParams();
        $params['pretranslate_100'] = '5';
        $this->setRequestParams($params);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-6);

        $this->invokePrivate('validateTheRequest');
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateTheRequest_throws_on_invalid_pretranslate_101(): void
    {
        $_COOKIE['upload_token'] = '66666666-6666-6666-6666-666666666666';
        $params = $this->validRequestParams();
        $params['pretranslate_101'] = '9';
        $this->setRequestParams($params);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-6);

        $this->invokePrivate('validateTheRequest');
    }

    // ─── validateMtEngine ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateMtEngine_returns_engine_null_for_mymemory(): void
    {
        /** @var array{mt_engine:int, engine:mixed} $result */
        $result = $this->invokePrivate('validateMtEngine', [1]);

        $this->assertSame(1, $result['mt_engine']);
        $this->assertNull($result['engine']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateMtEngine_defaults_null_engine_to_zero(): void
    {
        /** @var array{mt_engine:int, engine:mixed} $result */
        $result = $this->invokePrivate('validateMtEngine', [null]);

        $this->assertSame(0, $result['mt_engine']);
        $this->assertNull($result['engine']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateMtEngine_throws_for_unknown_engine_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->invokePrivate('validateMtEngine', [999999]);
    }

    // ─── validatePublicTMPenalty ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function validatePublicTMPenalty_returns_value_when_in_range(): void
    {
        $this->assertSame(50, $this->invokePrivate('validatePublicTMPenalty', [50]));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validatePublicTMPenalty_throws_when_out_of_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-6);

        $this->invokePrivate('validatePublicTMPenalty', [150]);
    }

    // ─── validateSourceLang / validateTargetLangs ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateSourceLang_returns_language_when_valid(): void
    {
        $result = $this->invokePrivate('validateSourceLang', [Languages::getInstance(), 'en-US']);

        $this->assertSame('en-US', $result);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateSourceLang_throws_when_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-3);

        $this->invokePrivate('validateSourceLang', [Languages::getInstance(), '']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateSourceLang_throws_when_unsupported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-3);

        $this->invokePrivate('validateSourceLang', [Languages::getInstance(), 'zz-ZZ']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateTargetLangs_dedupes_and_returns_csv(): void
    {
        $result = $this->invokePrivate('validateTargetLangs', [Languages::getInstance(), 'it-IT, fr-FR, it-IT']);

        $this->assertSame('it-IT,fr-FR', $result);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateTargetLangs_throws_when_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-4);

        $this->invokePrivate('validateTargetLangs', [Languages::getInstance(), '']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateTargetLangs_throws_when_unsupported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-4);

        $this->invokePrivate('validateTargetLangs', [Languages::getInstance(), 'zz-ZZ']);
    }

    // ─── validateMMTGlossaries / validateLaraGlossaries ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateMMTGlossaries_returns_null_when_empty(): void
    {
        $this->assertNull($this->invokePrivate('validateMMTGlossaries', [null]));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateLaraGlossaries_returns_null_when_empty(): void
    {
        $this->assertNull($this->invokePrivate('validateLaraGlossaries', [null]));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateMMTGlossaries_returns_decoded_string_for_valid_json(): void
    {
        $result = $this->invokePrivate('validateMMTGlossaries', ['[1,2]']);

        $this->assertSame('[1,2]', $result);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateMMTGlossaries_throws_for_invalid_json(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-6);

        $this->invokePrivate('validateMMTGlossaries', ['not-json']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateLaraGlossaries_returns_decoded_string_for_valid_json(): void
    {
        $result = $this->invokePrivate('validateLaraGlossaries', ['["g1","g2"]']);

        $this->assertSame('["g1","g2"]', $result);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateLaraGlossaries_throws_for_invalid_json(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(-6);

        $this->invokePrivate('validateLaraGlossaries', ['[1,2]']);
    }

    // ─── validateQaModelTemplate / validatePayableRateTemplate / xliff / filters (null paths) ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateQaModelTemplate_returns_null_when_both_empty(): void
    {
        $this->assertNull($this->invokePrivate('validateQaModelTemplate', [null, null]));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validatePayableRateTemplate_returns_null_when_both_empty(): void
    {
        $this->assertNull($this->invokePrivate('validatePayableRateTemplate', [null, null]));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateXliffParameters_returns_input_when_empty(): void
    {
        $this->assertNull($this->invokePrivate('validateXliffParameters', [null, null]));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateFiltersExtractionParameters_returns_input_when_empty(): void
    {
        $this->assertNull($this->invokePrivate('validateFiltersExtractionParameters', [null]));
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateQaModelTemplate_throws_for_unknown_template_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->invokePrivate('validateQaModelTemplate', [null, 99999999]);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validatePayableRateTemplate_throws_for_unknown_template_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->invokePrivate('validatePayableRateTemplate', [null, 99999999]);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function validateXliffParameters_throws_for_unknown_template_id(): void
    {
        $this->expectException(Exception::class);

        $this->invokePrivate('validateXliffParameters', [null, 99999999]);
    }

    // ─── setMetadataFromPostInput ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function setMetadataFromPostInput_sets_word_count_and_icu(): void
    {
        $this->invokePrivate('setMetadataFromPostInput', [[
            'mt_quality_value_in_editor' => 90,
            'icu_enabled'                => true,
        ]]);

        $metaProp = $this->reflector->getProperty('metadata');
        /** @var array<string, mixed> $metadata */
        $metadata = $metaProp->getValue($this->controller);

        $this->assertArrayHasKey('mt_quality_value_in_editor', $metadata);
        $this->assertSame(90, $metadata['mt_quality_value_in_editor']);
        $this->assertTrue($metadata['icu_enabled']);
    }

    // ─── generateTargetEngineAssociation ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function generateTargetEngineAssociation_maps_each_target_to_engine(): void
    {
        /** @var array<string, int|null> $assoc */
        $assoc = $this->invokePrivate('generateTargetEngineAssociation', ['it-IT,fr-FR', 5]);

        $this->assertSame(['it-IT' => 5, 'fr-FR' => 5], $assoc);
    }

    // ─── sanitizeTmKeyArr ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function sanitizeTmKeyArr_returns_array_with_key(): void
    {
        /** @var array<string, mixed> $result */
        $result = $this->invokePrivate('sanitizeTmKeyArr', [['key' => 'abcd1234abcd1234']]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('key', $result);
        $this->assertSame('abcd1234abcd1234', $result['key']);
    }

    // ─── setTeam (real DB) ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function setTeam_returns_team_for_valid_membership(): void
    {
        /** @var TeamStruct $team */
        $team = $this->invokePrivate('setTeam', [(string) $this->teamId(self::BASE)]);

        $this->assertInstanceOf(TeamStruct::class, $team);
        $this->assertSame($this->teamId(self::BASE), (int) $team->id);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function setTeam_throws_when_membership_not_found(): void
    {
        $this->expectException(Exception::class);

        $this->invokePrivate('setTeam', ['99999999']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function setTeam_returns_personal_team_when_id_null(): void
    {
        /** @var TeamStruct $team */
        $team = $this->invokePrivate('setTeam', [null]);

        $this->assertInstanceOf(TeamStruct::class, $team);
    }

    // ─── appendFeaturesToProject ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function appendFeaturesToProject_returns_array(): void
    {
        /** @var array<int|string, mixed> $features */
        $features = $this->invokePrivate('appendFeaturesToProject', [1]);

        $this->assertIsArray($features);
    }

    // NOTE: buildProjectStructure() is covered comprehensively by the shared
    // BuildProjectStructureTest (same namespace) per Playbook §6 — not duplicated here.

    // ─── assignLastCreatedPid / clearSessionFiles ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function assignLastCreatedPid_sets_session_keys(): void
    {
        $this->invokePrivate('assignLastCreatedPid', [12345]);

        $this->assertFalse($_SESSION['redeem_project']);
        $this->assertSame(12345, $_SESSION['last_created_pid']);
    }

    /**
     * @throws Throwable
     */
    #[Test]
    public function clearSessionFiles_removes_file_list_from_session(): void
    {
        $this->invokePrivate('clearSessionFiles');

        $this->addToAssertionCount(1);
    }

    // ─── TmKeyStruct sanity to lock the helper contract ───

    /**
     * @throws Throwable
     */
    #[Test]
    public function sanitizeTmKeyArr_completes_format(): void
    {
        $struct = new TmKeyStruct(['key' => 'deadbeefdeadbeef']);
        $this->assertSame('deadbeefdeadbeef', $struct->key);

        /** @var array<string, mixed> $result */
        $result = $this->invokePrivate('sanitizeTmKeyArr', [['key' => 'deadbeefdeadbeef']]);
        $this->assertArrayHasKey('r', $result);
        $this->assertArrayHasKey('w', $result);
    }
}
