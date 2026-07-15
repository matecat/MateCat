<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\ConvertFileController;
use Exception;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Matecat\TestHelpers\ControllerSeedFragments;
use Model\DataAccess\Database;
use Model\Filters\FiltersConfigTemplateStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Utils\Logger\MatecatLogger;

class TestableConvertFileController extends ConvertFileController
{
    public function __construct()
    {
    }

    protected function registerValidators(): void
    {
    }
}

/**
 * Real-DB suite for ConvertFileController.
 *
 * Reserved ID block base = 9022000 (Wave 4, N=22).
 *   base+6 user/uid (9022006) — owner of the seeded filters template.
 * Per-suite owner email: ctrltest_9022000@example.org (Playbook §4).
 * Cleans ONLY reserved ids; never by shared keys.
 */
#[AllowMockObjectsWithoutExpectations]
class ConvertFileControllerTest extends AbstractTest
{
    use ControllerSeedFragments;

    private const int BASE = 9022000;
    private const int TEMPLATE_ID = 9022100;

    /** @var ReflectionClass<ConvertFileController> */
    private ReflectionClass $reflector;
    private TestableConvertFileController $controller;
    private Request $requestStub;
    private Response&MockObject $responseMock;

    /**
     * @throws ReflectionException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanTestData();
        $this->seedTestData();

        $this->controller = new TestableConvertFileController();
        $this->reflector  = new ReflectionClass(ConvertFileController::class);

        $this->requestStub  = new Request();
        $this->responseMock = $this->createMock(Response::class);

        $this->setProp('request', $this->requestStub);
        $this->setProp('response', $this->responseMock);
        $this->setProp('database', obtainTestDatabase());

        $user        = new UserStruct();
        $user->uid   = $this->userId(self::BASE);
        $user->email = $this->ownerEmail(self::BASE);
        $this->setProp('user', $user);

        $this->setProp('logger', $this->createMock(MatecatLogger::class));
        $this->setProp('featureSet', new FeatureSet(obtainTestDatabase()));
    }

    protected function tearDown(): void
    {
        $this->cleanTestData();
        parent::tearDown();
    }

    private function seedTestData(): void
    {
        $this->seedUser(self::BASE, $this->ownerEmail(self::BASE));

        $uid = $this->userId(self::BASE);
        obtainTestDatabase()->getConnection()->exec(
            "INSERT IGNORE INTO filters_config_templates (id, name, uid, json, created_at, modified_at) "
            . "VALUES (" . self::TEMPLATE_ID . ", 'CtrlConvertTpl', $uid, '{}', NOW(), NOW())"
        );
    }

    private function cleanTestData(): void
    {
        obtainTestDatabase()->getConnection()->exec(
            "DELETE FROM filters_config_templates WHERE id = " . self::TEMPLATE_ID
        );
        $this->cleanFragments(self::BASE);
    }

    private function setProp(string $name, mixed $value): void
    {
        $prop = $this->reflector->getProperty($name);
        $prop->setValue($this->controller, $value);
    }

    /**
     * @param array<string, string> $params
     */
    private function setRequestParams(array $params): void
    {
        $serverParams = ['REQUEST_URI' => '/api/app/convertFile', 'REQUEST_METHOD' => 'POST'];
        $this->requestStub = new Request([], $params, [], $serverParams);
        $this->setProp('request', $this->requestStub);
    }

    /**
     * @param array<int, mixed> $args
     *
     * @throws ReflectionException
     */
    private function invokePrivate(string $method, array $args = []): mixed
    {
        $m = $this->reflector->getMethod($method);

        return $m->invoke($this->controller, ...$args);
    }

    /**
     * @return array<string, string>
     */
    private function validParams(): array
    {
        return [
            'file_name'         => 'document.docx',
            'source_lang'       => 'en-US',
            'target_lang'       => 'it-IT',
            'segmentation_rule' => 'standard',
            'icu_enabled'       => '1',
        ];
    }

    // ─── validateTheRequest: failure branches ───

    #[Test]
    public function validateTheRequest_throws_when_file_name_missing(): void
    {
        $params = $this->validParams();
        unset($params['file_name']);
        $this->setRequestParams($params);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing file name.');

        $this->invokePrivate('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_throws_when_source_lang_missing(): void
    {
        $params = $this->validParams();
        unset($params['source_lang']);
        $this->setRequestParams($params);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing source language.');

        $this->invokePrivate('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_throws_when_target_lang_missing(): void
    {
        $params = $this->validParams();
        unset($params['target_lang']);
        $this->setRequestParams($params);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing target language.');

        $this->invokePrivate('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_throws_when_segmentation_rule_missing(): void
    {
        $params = $this->validParams();
        unset($params['segmentation_rule']);
        $this->setRequestParams($params);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing segmentation rule.');

        $this->invokePrivate('validateTheRequest');
    }

    #[Test]
    public function validateTheRequest_throws_when_file_name_invalid(): void
    {
        $params              = $this->validParams();
        $params['file_name'] = "../../etc/passwd\0";
        $this->setRequestParams($params);

        $this->expectException(InvalidArgumentException::class);

        $this->invokePrivate('validateTheRequest');
    }

    // ─── validateTheRequest: happy path ───

    #[Test]
    public function validateTheRequest_returns_expected_shape_and_values(): void
    {
        $this->setRequestParams($this->validParams());

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertIsArray($result);
        $this->assertSame('document.docx', $result['file_name']);
        $this->assertSame('en-US', $result['source_lang']);
        $this->assertSame('it-IT', $result['target_lang']);
        // 'standard' is normalized to null by Constants::validateSegmentationRules
        $this->assertNull($result['segmentation_rule']);
        $this->assertTrue($result['icu_enabled']);
        $this->assertFalse($result['restarted_conversion']);
        $this->assertSame(0, $result['filters_extraction_parameters_template_id']);
        $this->assertNull($result['filters_extraction_parameters']);
    }

    #[Test]
    public function validateTheRequest_resolves_filters_template_by_id_from_db(): void
    {
        $params                                              = $this->validParams();
        $params['filters_extraction_parameters_template_id'] = (string) self::TEMPLATE_ID;
        $this->setRequestParams($params);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertInstanceOf(FiltersConfigTemplateStruct::class, $result['filters_extraction_parameters']);
        $this->assertSame(self::TEMPLATE_ID, $result['filters_extraction_parameters']->id);
        $this->assertSame($this->userId(self::BASE), $result['filters_extraction_parameters']->uid);
        $this->assertSame(self::TEMPLATE_ID, $result['filters_extraction_parameters_template_id']);
    }

    #[Test]
    public function validateTheRequest_segmentation_rule_paragraph_is_preserved(): void
    {
        $params                      = $this->validParams();
        $params['segmentation_rule'] = 'paragraph';
        $this->setRequestParams($params);

        $result = $this->invokePrivate('validateTheRequest');

        $this->assertSame('paragraph', $result['segmentation_rule']);
    }

    #[Test]
    public function validateTheRequest_throws_on_unknown_segmentation_rule(): void
    {
        $params                      = $this->validParams();
        $params['segmentation_rule'] = 'bogus_rule';
        $this->setRequestParams($params);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(-4);

        $this->invokePrivate('validateTheRequest');
    }

    // ─── validateSourceLang / validateTargetLangs ───

    #[Test]
    public function validateSourceLang_returns_normalized_language(): void
    {
        $result = $this->invokePrivate('validateSourceLang', ['en-US']);

        $this->assertSame('en-US', $result);
    }

    #[Test]
    public function validateTargetLangs_returns_normalized_list(): void
    {
        $result = $this->invokePrivate('validateTargetLangs', ['it-IT']);

        $this->assertSame('it-IT', $result);
    }

    // ─── validateFiltersExtractionParametersTemplateId ───

    #[Test]
    public function validateFiltersTemplateId_returns_null_when_both_empty(): void
    {
        $result = $this->invokePrivate('validateFiltersExtractionParametersTemplateId', [null, null]);

        $this->assertNull($result);
    }

    #[Test]
    public function validateFiltersTemplateId_loads_struct_by_id(): void
    {
        $result = $this->invokePrivate(
            'validateFiltersExtractionParametersTemplateId',
            [null, self::TEMPLATE_ID]
        );

        $this->assertInstanceOf(FiltersConfigTemplateStruct::class, $result);
        $this->assertSame(self::TEMPLATE_ID, $result->id);
    }

    #[Test]
    public function validateFiltersTemplateId_throws_for_unknown_id(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('filters_extraction_parameters_template_id not valid');

        $this->invokePrivate('validateFiltersExtractionParametersTemplateId', [null, 88888888]);
    }

    #[Test]
    public function validateFiltersTemplateId_hydrates_struct_from_json(): void
    {
        $uid     = $this->userId(self::BASE);
        $encoded = json_encode(['name' => 'InlineTpl', 'uid' => $uid]);
        $this->assertIsString($encoded);
        $json = htmlspecialchars($encoded, ENT_QUOTES);

        $result = $this->invokePrivate('validateFiltersExtractionParametersTemplateId', [$json, null]);

        $this->assertInstanceOf(FiltersConfigTemplateStruct::class, $result);
        $this->assertSame('InlineTpl', $result->name);
        $this->assertSame($uid, $result->uid);
    }

    // ─── handle(): failure path (invalid upload token) ───

    #[Test]
    public function handle_throws_on_invalid_upload_token(): void
    {
        $this->setRequestParams($this->validParams());
        $_COOKIE['upload_token'] = 'not-a-valid-uuid';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid Upload Token.');

        try {
            $this->controller->handle();
        } finally {
            unset($_COOKIE['upload_token']);
        }
    }

    #[Test]
    public function handle_returns_error_payload_for_missing_source_file(): void
    {
        $this->setRequestParams($this->validParams());
        // Valid UUID-format token whose upload dir does not contain the file:
        // the conversion produces an error result (file not found) rather than
        // a fatal, exercising handle()'s error-response branch end-to-end.
        $_COOKIE['upload_token'] = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

        $this->responseMock->expects($this->once())
            ->method('code')
            ->with(400);

        $this->responseMock->expects($this->once())
            ->method('json')
            ->with($this->callback(function (array $data): bool {
                $this->assertArrayHasKey('errors', $data);
                $this->assertArrayHasKey('warnings', $data);
                $this->assertArrayHasKey('data', $data);
                $this->assertNotEmpty($data['errors']);
                return true;
            }));

        try {
            $this->controller->handle();
        } finally {
            unset($_COOKIE['upload_token']);
        }
    }
}
