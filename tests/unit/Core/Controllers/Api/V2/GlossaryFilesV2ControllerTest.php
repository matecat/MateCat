<?php

declare(strict_types=1);

namespace Matecat\Core\Controllers\Api\V2;

use Controller\API\Commons\Exceptions\ValidationError;
use Controller\API\V2\GlossaryFilesController;
use Exception;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use Model\Conversion\UploadElement;
use Model\Users\UserStruct;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use RuntimeException;
use Utils\Engines\Results\MyMemory\ExportResponse;
use Utils\TMS\TMSService;

/**
 * Testable subclass: the empty constructor bypasses Klein DI wiring so
 * properties can be injected via reflection (matching the OAuth/DeepLGlossary
 * test pattern). initDependencies() is a no-op so the real
 * `new TMSService($this->getDatabase())` (GlossaryFilesController:53) is never
 * executed — the TMService property is instead reflection-injected with a stub.
 * registerValidators() is a no-op so no LoginValidator/DB is required.
 */
class TestableGlossaryFilesController extends GlossaryFilesController
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
 * A subclass that keeps the real initDependencies()/registerValidators()
 * bodies, used to exercise those seams directly via reflection.
 */
class RealSeamGlossaryFilesController extends GlossaryFilesController
{
    public function __construct()
    {
    }

    protected function initDependencies(): void
    {
    }
}

/**
 * GlossaryFilesController test (API V2 coverage).
 *
 * The controller instantiates `new Upload()` inline (no injectable seam), so
 * check()/import() are exercised end-to-end with real temp XLSX files that flow
 * through the real Upload -> extractCSV (PhpSpreadsheet) -> GlossaryCSVValidator
 * pipeline. Only the TMSService network boundary is stubbed (reflection-injected).
 *
 * No DB is required: initDependencies()/registerValidators() are no-ops in the
 * Testable subclass and getDatabase() is never reached.
 */
class GlossaryFilesV2ControllerTest extends AbstractTest
{
    private ReflectionClass $reflector;
    private TestableGlossaryFilesController $controller;
    private Response $response;
    private TMSService $tmServiceStub;

    /** @var list<string> temp files created during tests, removed in tearDown */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new TestableGlossaryFilesController();
        $this->reflector  = new ReflectionClass(GlossaryFilesController::class);
        $this->response   = new Response();

        $this->tmServiceStub = $this->createStub(TMSService::class);

        $this->setProp('response', $this->response);
        $this->setProp('TMService', $this->tmServiceStub);
        $this->setProp('params', []);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        $this->tempFiles = [];

        // extractCSV() copies into fresh tempnam("/tmp", "MAT_EXCEL_GLOSS_") files;
        // Upload copies into $STORAGE_DIR/upload/<uuid>/ dirs. Clean both so no junk
        // leaks out of the test run.
        foreach (glob(sys_get_temp_dir() . '/MAT_EXCEL_GLOSS_*') ?: [] as $leaked) {
            @unlink($leaked);
        }

        parent::tearDown();
    }

    private function setProp(string $name, mixed $value): void
    {
        $this->reflector->getProperty($name)->setValue($this->controller, $value);
    }

    /**
     * Build a real .xlsx glossary file on disk and return its path.
     *
     * @param list<list<string>> $rows
     */
    private function makeXlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        foreach ($rows as $r => $cols) {
            foreach ($cols as $c => $val) {
                $sheet->setCellValue([$c + 1, $r + 1], $val);
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'gloss_xlsx_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * Craft a Klein request whose files() collection carries a real uploaded
     * XLSX, shaped like a $_FILES entry.
     */
    private function requestWithXlsx(string $xlsxPath): Request
    {
        return new Request(
            [],
            [],
            [],
            [],
            [
                'glossary' => [
                    'name'     => 'glossary.xlsx',
                    'tmp_name' => $xlsxPath,
                    'type'     => 'application/octet-stream',
                    'error'    => 0,
                    'size'     => filesize($xlsxPath),
                ],
            ]
        );
    }

    /**
     * @return list<list<string>> a valid 3-language glossary
     */
    private function validGlossaryRows(): array
    {
        return [
            ['en-GB', 'es-ES', 'it-IT'],
            ['Contact', 'Contactar', 'Contatta'],
            ['Support', 'Soporte', 'Supporto'],
        ];
    }

    private function fixture(string $name): string
    {
        return self::projectRoot() . '/tests/resources/files/csv/glossary/' . $name;
    }

    /**
     * Invoke a protected/private method with output buffering to swallow the
     * echo produced by Response::send().
     */
    private function invoke(string $method, array $args = []): mixed
    {
        $ref = $this->reflector->getMethod($method);

        ob_start();
        try {
            return $ref->invokeArgs($this->controller, $args);
        } finally {
            ob_end_clean();
        }
    }

    // ── validateRequest ──────────────────────────────────────────────────

    #[Test]
    public function validateRequest_filters_and_assigns_request_params(): void
    {
        $this->setProp('request', new Request([
            'tm_key'        => 'abc123key',
            'name'          => 'My Glossary',
            'downloadToken' => 'tok-42',
        ]));

        $this->invoke('validateRequest');

        self::assertSame('My Glossary', $this->reflector->getProperty('name')->getValue($this->controller));
        self::assertSame('abc123key', $this->reflector->getProperty('tm_key')->getValue($this->controller));
        self::assertSame('tok-42', $this->reflector->getProperty('downloadToken')->getValue($this->controller));
    }

    // ── setSuccessResponse ───────────────────────────────────────────────

    #[Test]
    public function setSuccessResponse_sets_code_and_success_envelope(): void
    {
        $this->invoke('setSuccessResponse', [201, ['foo' => 'bar']]);

        self::assertSame(201, $this->response->code());
        $decoded = json_decode((string)$this->response->body(), true);
        self::assertSame(['errors' => [], 'data' => ['foo' => 'bar'], 'success' => true], $decoded);
    }

    // ── check ────────────────────────────────────────────────────────────

    #[Test]
    public function check_returns_results_for_valid_glossary(): void
    {
        $this->setProp('request', $this->requestWithXlsx($this->makeXlsx($this->validGlossaryRows())));
        $this->setProp('tm_key', 'abc123key');
        $this->setProp('name', 'My Glossary');

        $this->invoke('check');

        $decoded = json_decode((string)$this->response->body(), true);
        self::assertArrayHasKey('results', $decoded);
        self::assertCount(1, $decoded['results']);
        self::assertSame('My Glossary', $decoded['results'][0]['name']);
        self::assertSame('abc123key', $decoded['results'][0]['tmKey']);
        self::assertSame(3, $decoded['results'][0]['numberOfLanguages']);
    }

    #[Test]
    public function check_throws_when_tm_key_is_empty(): void
    {
        $this->setProp('request', $this->requestWithXlsx($this->makeXlsx($this->validGlossaryRows())));
        $this->setProp('tm_key', null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`TM key` field is mandatory');

        $this->invoke('check');
    }

    // ── import ───────────────────────────────────────────────────────────

    #[Test]
    public function import_loads_glossary_and_returns_202(): void
    {
        $this->setProp('request', $this->requestWithXlsx($this->makeXlsx($this->validGlossaryRows())));
        $this->setProp('tm_key', 'abc123key');
        $this->setProp('name', 'My Glossary');

        // addGlossaryInMyMemory() is void; the stub is a no-op boundary.
        $this->invoke('import');

        self::assertSame(202, $this->response->code());
        $decoded = json_decode((string)$this->response->body(), true);
        self::assertTrue($decoded['success']);
        self::assertArrayHasKey('uuids', $decoded['data']);
        self::assertCount(1, $decoded['data']['uuids']);
        self::assertSame(3, $decoded['data']['uuids'][0]['numberOfLanguages']);
    }

    #[Test]
    public function import_throws_when_tm_key_is_empty(): void
    {
        $this->setProp('request', $this->requestWithXlsx($this->makeXlsx($this->validGlossaryRows())));
        $this->setProp('tm_key', null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`TM key` field is mandatory');

        $this->invoke('import');
    }

    // ── validateCSVFile ──────────────────────────────────────────────────

    #[Test]
    public function validateCSVFile_returns_validator_for_valid_csv(): void
    {
        $validator = $this->invoke('validateCSVFile', [$this->fixture('V - Formato semplice solo lingue.csv')]);

        self::assertInstanceOf(\Utils\Validator\GlossaryCSVValidator::class, $validator);
        self::assertTrue($validator->isValid());
    }

    #[Test]
    public function validateCSVFile_throws_validation_error_for_invalid_csv(): void
    {
        $this->expectException(ValidationError::class);

        $this->invoke('validateCSVFile', [$this->fixture('NV - Header vuoto.csv')]);
    }

    // ── extractCSV ───────────────────────────────────────────────────────

    #[Test]
    public function extractCSV_converts_xlsx_to_csv(): void
    {
        $inner            = new UploadElement();
        $inner->file_path = $this->makeXlsx($this->validGlossaryRows());
        $element          = new UploadElement();
        $element->glossary = $inner;

        $result = $this->invoke('extractCSV', [$element]);

        self::assertInstanceOf(UploadElement::class, $result);
        foreach ($result as $fileInfo) {
            self::assertStringContainsString('MAT_EXCEL_GLOSS_', $fileInfo->file_path);
            self::assertFileExists($fileInfo->file_path);
            $csv = file_get_contents($fileInfo->file_path);
            self::assertStringContainsString('en-GB', (string)$csv);
        }
    }

    #[Test]
    public function extractCSV_throws_validation_error_on_unreadable_file(): void
    {
        // A path that does not exist -> IOFactory::createReaderForFile() throws a
        // PhpSpreadsheet Reader\Exception (a \Exception), which extractCSV wraps
        // into a ValidationError.
        $missing = sys_get_temp_dir() . '/gloss_missing_' . uniqid('', true) . '.xlsx';

        $inner             = new UploadElement();
        $inner->file_path  = $missing;
        $element           = new UploadElement();
        $element->glossary = $inner;

        $this->expectException(ValidationError::class);

        $this->invoke('extractCSV', [$element]);
    }

    // ── importStatus ─────────────────────────────────────────────────────

    #[Test]
    public function importStatus_returns_200_when_completed(): void
    {
        $this->tmServiceStub->method('glossaryUploadStatus')
            ->willReturn(['completed' => true, 'data' => ['status' => 'done']]);
        $this->setProp('params', ['uuid' => 'uuid-1']);

        $this->invoke('importStatus');

        self::assertSame(200, $this->response->code());
        $decoded = json_decode((string)$this->response->body(), true);
        self::assertSame(['status' => 'done'], $decoded['data']);
    }

    #[Test]
    public function importStatus_returns_202_when_not_completed(): void
    {
        $this->tmServiceStub->method('glossaryUploadStatus')
            ->willReturn(['completed' => false, 'data' => ['status' => 'loading']]);
        $this->setProp('params', ['uuid' => 'uuid-2']);

        $this->invoke('importStatus');

        self::assertSame(202, $this->response->code());
    }

    // ── download ─────────────────────────────────────────────────────────

    private function injectUser(?string $email): void
    {
        $user             = new UserStruct();
        $user->email      = $email;
        $user->first_name = 'Jane';
        $user->last_name  = 'Doe';
        $this->setProp('user', $user);
    }

    #[Test]
    public function download_returns_success_response_on_200(): void
    {
        $this->setProp('tm_key', 'abc123key');
        $this->setProp('name', 'My Glossary');
        $this->injectUser('jane@example.org');

        $this->tmServiceStub->method('glossaryExport')
            ->willReturn(new ExportResponse(['responseStatus' => 200, 'responseData' => ['link' => 'x']]));

        $this->invoke('download');

        self::assertSame(200, $this->response->code());
        $decoded = json_decode((string)$this->response->body(), true);
        self::assertTrue($decoded['success']);
        self::assertSame(['link' => 'x'], $decoded['data']);
    }

    #[Test]
    public function download_throws_when_tm_key_is_empty(): void
    {
        $this->setProp('tm_key', null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('`TM key` field is mandatory');

        $this->invoke('download');
    }

    #[Test]
    public function download_throws_when_user_email_is_null(): void
    {
        $this->setProp('tm_key', 'abc123key');
        $this->injectUser(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User email is required');

        $this->invoke('download');
    }

    #[Test]
    public function download_throws_when_export_fails(): void
    {
        $this->setProp('tm_key', 'abc123key');
        $this->injectUser('jane@example.org');

        $this->tmServiceStub->method('glossaryExport')
            ->willReturn(new ExportResponse(['responseStatus' => 500, 'responseData' => 'err']));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error while requesting export');

        $this->invoke('download');
    }

    // ── registerValidators (real seam) ───────────────────────────────────

    #[Test]
    public function registerValidators_appends_login_validator(): void
    {
        $controller = new RealSeamGlossaryFilesController();
        $ref        = new ReflectionClass(GlossaryFilesController::class);

        $ref->getProperty('request')->setValue($controller, new Request());
        $ref->getProperty('response')->setValue($controller, new Response());
        $ref->getProperty('params')->setValue($controller, []);

        $ref->getMethod('registerValidators')->invoke($controller);

        $validators = $ref->getProperty('validators')->getValue($controller);
        self::assertCount(1, $validators);
    }
}
