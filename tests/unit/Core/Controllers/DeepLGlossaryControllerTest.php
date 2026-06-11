<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers;

use Controller\API\V3\DeepLGlossaryController;
use Exception;
use Klein\DataCollection\DataCollection;
use Klein\HttpStatus;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\Conversion\Upload;
use Model\Conversion\UploadElement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use ReflectionClass;
use Utils\Engines\DeepL;
use Utils\Logger\MatecatLogger;
use Utils\Registry\AppConfig;

class TestableDeepLGlossaryController extends DeepLGlossaryController
{
    public DeepL $stubClient;

    public function __construct()
    {
    }

    protected function afterConstruct(): void
    {
    }

    protected function initDependencies(): void
    {
    }

    protected function registerValidators(): void
    {
    }

    protected function getDeepLClient(int $engineId): DeepL
    {
        return $this->stubClient;
    }
}

class ValidatorTestableDeepLGlossaryController extends DeepLGlossaryController
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }
}

class DeepLGlossaryControllerTest extends AbstractTest
{
    private ReflectionClass $reflector;
    private TestableDeepLGlossaryController $controller;
    private Stub&DeepL $deepLStub;

    protected function setUp(): void
    {
        parent::setUp();

        AppConfig::$SKIP_SQL_CACHE = true;

        $this->controller = new TestableDeepLGlossaryController();
        $this->reflector  = new ReflectionClass(DeepLGlossaryController::class);

        $this->reflector->getProperty('logger')->setValue($this->controller, $this->createStub(MatecatLogger::class));

        $this->deepLStub = $this->createStub(DeepL::class);
        $this->controller->stubClient = $this->deepLStub;
    }

    protected function tearDown(): void
    {
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    /**
     * @param array<string, string> $params
     */
    private function setRequest(array $params): void
    {
        $request = $this->createStub(Request::class);
        $request->method('param')->willReturnCallback(
            static fn(string $key, $default = null) => $params[$key] ?? $default
        );
        $this->reflector->getProperty('request')->setValue($this->controller, $request);
    }

    private function injectResponseMock(): Response&\PHPUnit\Framework\MockObject\MockObject
    {
        $mock = $this->createMock(Response::class);
        $mock->method('status')->willReturn($this->createStub(HttpStatus::class));
        $this->reflector->getProperty('response')->setValue($this->controller, $mock);
        return $mock;
    }

    // ── all ──────────────────────────────────────────────────────────────

    #[Test]
    public function all_returns_glossaries_json(): void
    {
        $this->setRequest(['engineId' => '7']);
        $this->deepLStub->method('glossaries')->willReturn([['glossary_id' => 'g1']]);

        $response = $this->injectResponseMock();
        $response->expects(self::once())
            ->method('json')
            ->with([['glossary_id' => 'g1']]);

        $this->controller->all();
    }

    // ── delete ───────────────────────────────────────────────────────────

    #[Test]
    public function delete_returns_id_json(): void
    {
        $this->setRequest(['engineId' => '7', 'id' => 'abc123']);

        $response = $this->injectResponseMock();
        $response->expects(self::once())
            ->method('json')
            ->with(['id' => 'abc123']);

        $this->controller->delete();
    }

    // ── get ──────────────────────────────────────────────────────────────

    #[Test]
    public function get_returns_glossary_json(): void
    {
        $this->setRequest(['engineId' => '7', 'id' => 'abc123']);
        $this->deepLStub->method('getGlossary')->willReturn(['glossary_id' => 'abc123', 'name' => 'G']);

        $response = $this->injectResponseMock();
        $response->expects(self::once())
            ->method('json')
            ->with(['glossary_id' => 'abc123', 'name' => 'G']);

        $this->controller->get();
    }

    // ── getEntries ───────────────────────────────────────────────────────

    #[Test]
    public function getEntries_returns_entries_json(): void
    {
        $this->setRequest(['engineId' => '7', 'id' => 'abc123']);
        $this->deepLStub->method('getGlossaryEntries')->willReturn(['Hello' => 'Ciao']);

        $response = $this->injectResponseMock();
        $response->expects(self::once())
            ->method('json')
            ->with(['Hello' => 'Ciao']);

        $this->controller->getEntries();
    }

    // ── create validation ────────────────────────────────────────────────

    #[Test]
    public function create_throws_when_glossary_file_missing(): void
    {
        $files = $this->createStub(DataCollection::class);
        $files->method('exists')->willReturn(false);

        $request = $this->createStub(Request::class);
        $request->method('files')->willReturn($files);
        $this->reflector->getProperty('request')->setValue($this->controller, $request);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing `glossary`');

        $this->controller->create();
    }

    #[Test]
    public function create_throws_when_name_missing(): void
    {
        $files = $this->createStub(DataCollection::class);
        $files->method('exists')->willReturn(true);

        $request = $this->createStub(Request::class);
        $request->method('files')->willReturn($files);
        $this->reflector->getProperty('request')->setValue($this->controller, $request);
        $this->reflector->getProperty('params')->setValue($this->controller, []);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Missing `name`');

        $this->controller->create();
    }

    #[Test]
    public function create_parses_csv_and_returns_created_glossary(): void
    {
        // real 2-column CSV the production code parses end-to-end
        $csvPath = tempnam(sys_get_temp_dir(), 'glosstest') . '.csv';
        file_put_contents($csvPath, "source,target\nHello,Ciao\n");

        $innerFile = new UploadElement();
        $innerFile->file_path = $csvPath;
        $uploaded = new UploadElement();
        $uploaded->glossary = $innerFile;

        $upload = $this->createStub(Upload::class);
        $upload->method('uploadFiles')->willReturn($uploaded);
        $this->reflector->getProperty('upload')->setValue($this->controller, $upload);

        $files = $this->createStub(DataCollection::class);
        $files->method('exists')->willReturn(true);
        $files->method('all')->willReturn([]);

        $request = $this->createStub(Request::class);
        $request->method('files')->willReturn($files);
        $request->method('param')->willReturn('7');
        $this->reflector->getProperty('request')->setValue($this->controller, $request);
        $this->reflector->getProperty('params')->setValue($this->controller, ['name' => 'My Glossary']);

        $this->deepLStub->method('createGlossary')->willReturn(['glossary_id' => 'new-id']);

        $response = $this->injectResponseMock();
        $response->expects(self::once())
            ->method('json')
            ->with(['glossary_id' => 'new-id']);

        $this->controller->create();
    }

    // ── registerValidators ───────────────────────────────────────────────

    #[Test]
    public function registerValidators_registers_login_validator(): void
    {
        $controller = new ValidatorTestableDeepLGlossaryController();
        $ref = new ReflectionClass(DeepLGlossaryController::class);

        $ref->getProperty('request')->setValue($controller, $this->createStub(Request::class));
        $ref->getProperty('response')->setValue($controller, $this->createStub(Response::class));
        $ref->getProperty('params')->setValue($controller, []);

        $ref->getMethod('registerValidators')->invoke($controller);

        $validators = $ref->getProperty('validators')->getValue($controller);
        self::assertCount(1, $validators);
    }
}
