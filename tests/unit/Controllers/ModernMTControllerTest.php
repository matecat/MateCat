<?php

namespace unit\Controllers;

use Controller\API\V3\ModernMTController;
use Exception;
use Klein\Request;
use Klein\Response;
use Klein\HttpStatus;
use Model\Conversion\Upload;
use Model\Conversion\UploadElement;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RuntimeException;
use TestHelpers\AbstractTest;
use Utils\Engines\MMT;
use Utils\Files\CSV as CSVParser;

class TestableModernMTController extends ModernMTController
{
    public ?MMT $fakeMMTClient = null;
    public ?Upload $fakeUploadManager = null;
    public ?string $fakeExtractCSVResult = null;

    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }

    protected function getModernMTClient(int $engineId): MMT
    {
        if ($this->fakeMMTClient === null) {
            throw new RuntimeException('fakeMMTClient not configured');
        }

        return $this->fakeMMTClient;
    }

    protected function createUploadManager(): Upload
    {
        if ($this->fakeUploadManager === null) {
            throw new RuntimeException('fakeUploadManager not configured');
        }

        return $this->fakeUploadManager;
    }

    protected function extractCSV(UploadElement $glossary): string
    {
        if ($this->fakeExtractCSVResult !== null) {
            return $this->fakeExtractCSVResult;
        }

        return parent::extractCSV($glossary);
    }
}

class ModernMTControllerTest extends AbstractTest
{
    private TestableModernMTController $controller;
    private ReflectionClass $reflector;
    private Request $requestStub;
    private Response $responseMock;
    private MMT $mmtStub;
    private mixed $lastJsonResponse = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lastJsonResponse = null;
        $this->controller = new TestableModernMTController();
        $this->reflector = new ReflectionClass(ModernMTController::class);

        $this->requestStub = $this->createStub(Request::class);
        $this->responseMock = $this->createStub(Response::class);
        $this->mmtStub = $this->createStub(MMT::class);

        $reqProp = $this->reflector->getProperty('request');
        $reqProp->setValue($this->controller, $this->requestStub);

        $resProp = $this->reflector->getProperty('response');
        $resProp->setValue($this->controller, $this->responseMock);

        $user = new UserStruct();
        $user->uid = 42;
        $userProp = $this->reflector->getProperty('user');
        $userProp->setValue($this->controller, $user);

        $this->controller->fakeMMTClient = $this->mmtStub;

        $statusStub = $this->createStub(HttpStatus::class);
        $this->responseMock->method('status')->willReturn($statusStub);
        $this->responseMock->method('json')->willReturnCallback(function (mixed $data): Response {
            $this->lastJsonResponse = $data;

            return $this->responseMock;
        });
    }

    private function stubRequestParam(string $key, string $value): void
    {
        $this->requestStub->method('param')->willReturnCallback(
            function (string $name) use ($key, $value): ?string {
                return $name === $key ? $value : null;
            }
        );
    }

    private function stubRequestParams(array $paramMap): void
    {
        $this->requestStub->method('param')->willReturnCallback(
            function (string $name) use ($paramMap): ?string {
                return $paramMap[$name] ?? null;
            }
        );
    }

    // ------- keys() -------

    #[Test]
    public function keys_returns_filtered_memories(): void
    {
        $this->stubRequestParams(['engineId' => '10']);
        $this->requestStub->method('params')->willReturn([]);

        $this->mmtStub->method('getAllMemories')->willReturn([
            ['id' => 1, 'name' => 'Alpha', 'hasGlossary' => 1],
            ['id' => 2, 'name' => 'Beta', 'hasGlossary' => 0],
        ]);

        $this->controller->keys();

        self::assertCount(2, $this->lastJsonResponse);
        self::assertSame(1, $this->lastJsonResponse[0]['id']);
        self::assertTrue($this->lastJsonResponse[0]['has_glossary']);
        self::assertFalse($this->lastJsonResponse[1]['has_glossary']);
    }

    #[Test]
    public function keys_filters_by_q_param(): void
    {
        $this->stubRequestParams(['engineId' => '10']);
        $this->requestStub->method('params')->willReturn(['q' => 'alp']);

        $this->mmtStub->method('getAllMemories')->willReturn([
            ['id' => 1, 'name' => 'Alpha', 'hasGlossary' => 0],
            ['id' => 2, 'name' => 'Beta', 'hasGlossary' => 0],
        ]);

        $this->controller->keys();

        self::assertCount(1, $this->lastJsonResponse);
        self::assertSame('Alpha', $this->lastJsonResponse[0]['name']);
    }

    #[Test]
    public function keys_returns_empty_when_getAllMemories_returns_null(): void
    {
        $this->stubRequestParams(['engineId' => '10']);
        $this->requestStub->method('params')->willReturn([]);

        $this->mmtStub->method('getAllMemories')->willReturn(null);

        $this->controller->keys();

        self::assertSame([], $this->lastJsonResponse);
    }

    // ------- importStatus() -------

    #[Test]
    public function importStatus_returns_job_status(): void
    {
        $this->stubRequestParams(['uuid' => 'test-uuid-123', 'engineId' => '5']);

        $this->mmtStub->method('importJobStatus')->willReturn(['status' => 'done']);

        $this->controller->importStatus();

        self::assertSame(['status' => 'done'], $this->lastJsonResponse);
    }

    #[Test]
    public function importStatus_throws_on_invalid_uuid(): void
    {
        $this->stubRequestParams(['uuid' => '', 'engineId' => '5']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid `uuid` param');
        $this->expectExceptionCode(400);

        $this->controller->importStatus();
    }

    // ------- createMemory() -------

    #[Test]
    public function createMemory_with_all_params(): void
    {
        $this->stubRequestParams(['engineId' => '1']);
        $this->controller->params = [
            'name'        => 'My Memory',
            'description' => 'A test memory',
            'external_id' => 'ext-123',
        ];

        $this->mmtStub->method('createMemory')->willReturn(['id' => 99, 'name' => 'My Memory']);

        $this->controller->createMemory();

        self::assertSame(99, $this->lastJsonResponse['id']);
    }

    #[Test]
    public function createMemory_throws_on_missing_name(): void
    {
        $this->stubRequestParams(['engineId' => '1']);
        $this->controller->params = [];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing `name` param');
        $this->expectExceptionCode(400);

        $this->controller->createMemory();
    }

    #[Test]
    public function createMemory_with_optional_params_null(): void
    {
        $this->stubRequestParams(['engineId' => '1']);
        $this->controller->params = ['name' => 'Simple'];

        $this->mmtStub->method('createMemory')->willReturn(['id' => 1]);

        $this->controller->createMemory();

        self::assertSame(1, $this->lastJsonResponse['id']);
    }

    // ------- updateMemory() -------

    #[Test]
    public function updateMemory_delegates_to_mmt_client(): void
    {
        $this->stubRequestParams(['memoryId' => '77', 'engineId' => '1']);
        $this->controller->params = ['name' => 'Updated Name'];

        $this->mmtStub->method('updateMemory')->willReturn(['id' => 77, 'name' => 'Updated Name']);

        $this->controller->updateMemory();

        self::assertSame('Updated Name', $this->lastJsonResponse['name']);
    }

    #[Test]
    public function updateMemory_throws_on_missing_name(): void
    {
        $this->stubRequestParams(['memoryId' => '77', 'engineId' => '1']);
        $this->controller->params = [];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing `name` param');

        $this->controller->updateMemory();
    }

    #[Test]
    public function updateMemory_throws_on_invalid_memoryId(): void
    {
        $this->stubRequestParams(['memoryId' => '', 'engineId' => '1']);
        $this->controller->params = ['name' => 'Test'];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid `memoryId` param');

        $this->controller->updateMemory();
    }

    // ------- deleteMemory() -------

    #[Test]
    public function deleteMemory_delegates_to_mmt_client(): void
    {
        $this->stubRequestParams(['memoryId' => '55', 'engineId' => '1']);

        $this->mmtStub->method('deleteMemory')->willReturn(['success' => true]);

        $this->controller->deleteMemory();

        self::assertTrue($this->lastJsonResponse['success']);
    }

    #[Test]
    public function deleteMemory_throws_on_invalid_memoryId(): void
    {
        $this->stubRequestParams(['memoryId' => '', 'engineId' => '1']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid `memoryId` param');

        $this->controller->deleteMemory();
    }

    // ------- modifyGlossary() -------

    #[Test]
    public function modifyGlossary_sends_payload(): void
    {
        $this->stubRequestParams(['engineId' => '1']);
        $this->controller->params = [
            'memoryId' => '33',
            'type'     => 'unidirectional',
            'terms'    => [
                ['term' => 'hello', 'language' => 'en'],
                ['term' => 'ciao', 'language' => 'it'],
            ],
        ];

        $this->mmtStub->method('updateGlossary')->willReturn(['status' => 'ok']);

        $this->controller->modifyGlossary();

        self::assertSame('ok', $this->lastJsonResponse['status']);
    }

    #[Test]
    public function modifyGlossary_includes_tuid_when_equivalent(): void
    {
        $this->stubRequestParams(['engineId' => '1']);
        $this->controller->params = [
            'memoryId' => '33',
            'type'     => 'equivalent',
            'tuid'     => 'tuid-abc',
            'terms'    => [
                ['term' => 'hello', 'language' => 'en'],
                ['term' => 'ciao', 'language' => 'it'],
            ],
        ];

        $this->mmtStub->method('updateGlossary')->willReturn(['status' => 'ok']);

        $this->controller->modifyGlossary();

        self::assertSame('ok', $this->lastJsonResponse['status']);
    }

    // ------- validateModifyGlossaryParams() -------

    #[Test]
    public function validateModifyGlossaryParams_throws_on_missing_memoryId(): void
    {
        $this->controller->params = ['type' => 'unidirectional'];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing `memoryId` param');

        $this->callPrivate('validateModifyGlossaryParams');
    }

    #[Test]
    public function validateModifyGlossaryParams_throws_on_missing_type(): void
    {
        $this->controller->params = ['memoryId' => '1'];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing `type` param');

        $this->callPrivate('validateModifyGlossaryParams');
    }

    #[Test]
    public function validateModifyGlossaryParams_throws_on_wrong_type(): void
    {
        $this->controller->params = ['memoryId' => '1', 'type' => 'invalid'];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Wrong `type` param');

        $this->callPrivate('validateModifyGlossaryParams');
    }

    #[Test]
    public function validateModifyGlossaryParams_throws_on_equivalent_without_tuid(): void
    {
        $this->controller->params = [
            'memoryId' => '1',
            'type'     => 'equivalent',
            'terms'    => [['term' => 'a', 'language' => 'en']],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing `tuid` param');

        $this->callPrivate('validateModifyGlossaryParams');
    }

    #[Test]
    public function validateModifyGlossaryParams_throws_on_missing_terms(): void
    {
        $this->controller->params = ['memoryId' => '1', 'type' => 'unidirectional'];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing `terms` param');

        $this->callPrivate('validateModifyGlossaryParams');
    }

    #[Test]
    public function validateModifyGlossaryParams_throws_on_non_array_terms(): void
    {
        $this->controller->params = ['memoryId' => '1', 'type' => 'unidirectional', 'terms' => 'invalid'];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('`terms` is not an array');

        $this->callPrivate('validateModifyGlossaryParams');
    }

    #[Test]
    public function validateModifyGlossaryParams_throws_on_malformed_terms(): void
    {
        $this->controller->params = [
            'memoryId' => '1',
            'type'     => 'unidirectional',
            'terms'    => [['foo' => 'bar']],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('`terms` array is malformed');

        $this->callPrivate('validateModifyGlossaryParams');
    }

    // ------- validateCSVContent() -------

    #[Test]
    public function validateCSVContent_passes_valid_unidirectional(): void
    {
        $csv = [['hello', 'ciao'], ['world', 'mondo']];

        $this->callPrivate('validateCSVContent', $csv);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function validateCSVContent_throws_on_empty_cell_unidirectional(): void
    {
        $csv = [['hello', '']];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Row 1 invalid, please add terms for both languages');

        $this->callPrivate('validateCSVContent', $csv);
    }

    #[Test]
    public function validateCSVContent_throws_on_missing_tuid_equivalent(): void
    {
        $csv = [['tuid', 'en', 'it'], ['', 'hello', 'ciao']];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Row 2 invalid, please provide a tuid for the row');

        $this->callPrivate('validateCSVContent', $csv);
    }

    #[Test]
    public function validateCSVContent_throws_on_insufficient_terms_equivalent(): void
    {
        $csv = [['tuid', 'en', 'it'], ['t1', 'hello', '']];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('please provide terms for at least two languages');

        $this->callPrivate('validateCSVContent', $csv);
    }

    // ------- getCsvType() -------

    #[Test]
    public function getCsvType_returns_unidirectional_for_two_columns(): void
    {
        $csv = [['hello', 'ciao']];

        $result = $this->callPrivate('getCsvType', $csv);
        self::assertSame('unidirectional', $result);
    }

    #[Test]
    public function getCsvType_returns_equivalent_for_tuid_with_three_plus_columns(): void
    {
        $csv = [['tuid', 'en', 'it']];

        $result = $this->callPrivate('getCsvType', $csv);
        self::assertSame('equivalent', $result);
    }

    #[Test]
    public function getCsvType_throws_on_single_column(): void
    {
        $csv = [['hello']];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('unidirectional glossaries should have exactly two columns');

        $this->callPrivate('getCsvType', $csv);
    }

    #[Test]
    public function getCsvType_throws_on_tuid_with_only_two_columns(): void
    {
        $csv = [['tuid', 'en']];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('at least two language columns');

        $this->callPrivate('getCsvType', $csv);
    }

    #[Test]
    public function getCsvType_throws_on_three_columns_without_tuid(): void
    {
        $csv = [['en', 'it', 'fr']];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('tuid column is expected');

        $this->callPrivate('getCsvType', $csv);
    }

    // ------- requireEngineId() -------

    #[Test]
    public function requireEngineId_returns_int_for_valid_input(): void
    {
        $this->stubRequestParam('engineId', '42');

        $result = $this->callPrivate('requireEngineId');

        self::assertSame(42, $result);
    }

    #[Test]
    public function requireEngineId_throws_on_empty_input(): void
    {
        $this->stubRequestParam('engineId', '');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid `engineId` param');

        $this->callPrivate('requireEngineId');
    }

    // ------- createMemoryAndImportGlossary() (null memory handling) -------

    #[Test]
    public function createMemoryAndImportGlossary_throws_on_null_memory(): void
    {
        $this->stubRequestParams(['engineId' => '1']);
        $this->controller->params = ['name' => 'Test'];

        $filesStub = $this->createStub(\Klein\DataCollection\DataCollection::class);
        $filesStub->method('exists')->willReturn(true);
        $this->requestStub->method('files')->willReturn($filesStub);

        $this->mmtStub->method('createMemory')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to create MMT memory');

        $this->controller->createMemoryAndImportGlossary();
    }

    // ------- filterResult() -------

    #[Test]
    public function filterResult_returns_true_without_q_param(): void
    {
        $result = $this->callPrivate('filterResult', [], ['name' => 'anything']);
        self::assertTrue($result);
    }

    #[Test]
    public function filterResult_returns_true_on_matching_q(): void
    {
        $result = $this->callPrivate('filterResult', ['q' => 'memo'], ['name' => 'My Memory']);
        self::assertTrue($result);
    }

    #[Test]
    public function filterResult_returns_false_on_non_matching_q(): void
    {
        $result = $this->callPrivate('filterResult', ['q' => 'xyz'], ['name' => 'My Memory']);
        self::assertFalse($result);
    }

    // ------- buildResult() -------

    #[Test]
    public function buildResult_maps_memory_fields(): void
    {
        $memory = ['id' => 7, 'name' => 'Test', 'hasGlossary' => 1];

        $result = $this->callPrivate('buildResult', $memory);

        self::assertSame(7, $result['id']);
        self::assertSame('Test', $result['name']);
        self::assertTrue($result['has_glossary']);
    }

    #[Test]
    public function buildResult_has_glossary_false_when_zero(): void
    {
        $memory = ['id' => 1, 'name' => 'X', 'hasGlossary' => 0];

        $result = $this->callPrivate('buildResult', $memory);

        self::assertFalse($result['has_glossary']);
    }

    // ------- validateImportGlossaryParams() -------

    #[Test]
    public function validateImportGlossaryParams_throws_on_missing_glossary_file(): void
    {
        $filesStub = $this->createStub(\Klein\DataCollection\DataCollection::class);
        $filesStub->method('exists')->willReturn(false);
        $this->requestStub->method('files')->willReturn($filesStub);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing `glossary` files');

        $this->callPrivate('validateImportGlossaryParams');
    }

    // ------- importGlossary() -------

    #[Test]
    public function importGlossary_throws_on_missing_memoryId(): void
    {
        $filesStub = $this->createStub(\Klein\DataCollection\DataCollection::class);
        $filesStub->method('exists')->willReturn(true);
        $this->requestStub->method('files')->willReturn($filesStub);

        $this->controller->params = [];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing `memoryId` param');

        $this->controller->importGlossary();
    }

    #[Test]
    public function importGlossary_throws_on_invalid_glossary_upload(): void
    {
        $this->stubRequestParams(['engineId' => '1']);

        $filesStub = $this->createStub(\Klein\DataCollection\DataCollection::class);
        $filesStub->method('exists')->willReturn(true);
        $filesStub->method('all')->willReturn([]);
        $this->requestStub->method('files')->willReturn($filesStub);

        $uploadResult = new UploadElement();
        $uploadStub = $this->createStub(Upload::class);
        $uploadStub->method('uploadFiles')->willReturn($uploadResult);
        $this->controller->fakeUploadManager = $uploadStub;

        $this->controller->params = ['memoryId' => '55'];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Glossary file upload failed');

        $this->controller->importGlossary();
    }

    #[Test]
    public function importGlossary_throws_on_empty_csv(): void
    {
        $this->stubRequestParams(['engineId' => '1']);

        $filesStub = $this->createStub(\Klein\DataCollection\DataCollection::class);
        $filesStub->method('exists')->willReturn(true);
        $filesStub->method('all')->willReturn([]);
        $this->requestStub->method('files')->willReturn($filesStub);

        $glossaryElement = new UploadElement(['file_path' => '/tmp/test.csv']);
        $uploadResult = new UploadElement();
        $uploadResult->glossary = $glossaryElement;

        $uploadStub = $this->createStub(Upload::class);
        $uploadStub->method('uploadFiles')->willReturn($uploadResult);
        $this->controller->fakeUploadManager = $uploadStub;
        $tmpFile = tempnam('/tmp', 'mmt_test_');
        file_put_contents($tmpFile, '');
        $this->controller->fakeExtractCSVResult = $tmpFile;

        $this->controller->params = ['memoryId' => '55'];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Glossary is empty');

        $this->controller->importGlossary();
    }

    // ------- createMemoryAndImportGlossary() -------

    #[Test]
    public function createMemoryAndImportGlossary_throws_on_missing_name(): void
    {
        $filesStub = $this->createStub(\Klein\DataCollection\DataCollection::class);
        $filesStub->method('exists')->willReturn(true);
        $this->requestStub->method('files')->willReturn($filesStub);

        $this->controller->params = [];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing `name` param');

        $this->controller->createMemoryAndImportGlossary();
    }

    #[Test]
    public function createMemoryAndImportGlossary_throws_on_invalid_name(): void
    {
        $filesStub = $this->createStub(\Klein\DataCollection\DataCollection::class);
        $filesStub->method('exists')->willReturn(true);
        $this->requestStub->method('files')->willReturn($filesStub);
        $this->stubRequestParams(['engineId' => '1']);

        $this->controller->params = ['name' => ''];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid `name` param');

        $this->controller->createMemoryAndImportGlossary();
    }

    #[Test]
    public function createMemoryAndImportGlossary_throws_on_memory_without_id(): void
    {
        $this->stubRequestParams(['engineId' => '1']);
        $this->controller->params = ['name' => 'Test'];

        $filesStub = $this->createStub(\Klein\DataCollection\DataCollection::class);
        $filesStub->method('exists')->willReturn(true);
        $this->requestStub->method('files')->willReturn($filesStub);

        $this->mmtStub->method('createMemory')->willReturn([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to create MMT memory');

        $this->controller->createMemoryAndImportGlossary();
    }

    #[Test]
    public function createMemoryAndImportGlossary_throws_on_invalid_glossary_upload(): void
    {
        $this->stubRequestParams(['engineId' => '1']);
        $this->controller->params = ['name' => 'Test'];

        $filesStub = $this->createStub(\Klein\DataCollection\DataCollection::class);
        $filesStub->method('exists')->willReturn(true);
        $filesStub->method('all')->willReturn([]);
        $this->requestStub->method('files')->willReturn($filesStub);

        $this->mmtStub->method('createMemory')->willReturn(['id' => 123]);

        $uploadResult = new UploadElement();
        $uploadStub = $this->createStub(Upload::class);
        $uploadStub->method('uploadFiles')->willReturn($uploadResult);
        $this->controller->fakeUploadManager = $uploadStub;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Glossary file upload failed');

        $this->controller->createMemoryAndImportGlossary();
    }

    // ------- modifyGlossary() edge cases -------

    #[Test]
    public function modifyGlossary_throws_on_invalid_memoryId(): void
    {
        $this->stubRequestParams(['engineId' => '1']);
        $this->controller->params = [
            'memoryId' => '',
            'type'     => 'unidirectional',
            'terms'    => [['term' => 'a', 'language' => 'en']],
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid `memoryId` param');

        $this->controller->modifyGlossary();
    }

    // ------- helper -------

    /**
     * @return mixed
     */
    private function callPrivate(string $method, mixed ...$args): mixed
    {
        $ref = $this->reflector->getMethod($method);

        return $ref->invoke($this->controller, ...$args);
    }
}
