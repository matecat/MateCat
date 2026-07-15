<?php

namespace Matecat\Core\Controllers\Api\V2;

use Controller\API\V2\SupportedFilesController;
use Klein\Request;
use Klein\Response;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;
use Utils\Registry\AppConfig;

/**
 * SupportedFilesController test (API V2 coverage).
 *
 * No DB access, no auth, no external services: getFileList() is a pure
 * iteration over the static AppConfig::$SUPPORTED_FILE_TYPES config array.
 * No DB-ID block needed.
 */
class TestableSupportedFilesController extends SupportedFilesController
{
    public function __construct()
    {
    }
}

class SupportedFilesV2ControllerTest extends AbstractTest
{
    private TestableSupportedFilesController $controller;
    private Response $response;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new TestableSupportedFilesController();
        $this->response   = new Response();
        $this->setProp('request', new Request([], [], [], ['REQUEST_URI' => '/api/v2/supported_files', 'REQUEST_METHOD' => 'GET']));
        $this->setProp('response', $this->response);
    }

    /**
     * @throws ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $prop = (new ReflectionClass(SupportedFilesController::class))->getProperty($name);
        $prop->setValue($this->controller, $value);
    }

    /**
     * @return array<string, list<list<array{ext: int|string, class: mixed}>>>
     *
     * @throws ReflectionException
     */
    private function invokeGetFileList(): array
    {
        $method = (new ReflectionClass(SupportedFilesController::class))->getMethod('getFileList');
        $method->setAccessible(true);

        return $method->invoke($this->controller);
    }

    /**
     * @return array<string, list<list<array{ext: int|string, class: mixed}>>>
     */
    private function buildExpectedFileList(): array
    {
        $ret = [];

        foreach (AppConfig::$SUPPORTED_FILE_TYPES as $key => $value) {
            $val = [];
            foreach ($value as $ext => $info) {
                $val[] = [
                    'ext'   => $ext,
                    'class' => $info[2],
                ];
            }

            $ret[$key] = array_chunk($val, 1);
        }

        return $ret;
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function getFileList_returns_expected_shape_matching_app_config(): void
    {
        $result = $this->invokeGetFileList();

        self::assertIsArray($result);
        self::assertNotEmpty($result);
        self::assertSame($this->buildExpectedFileList(), $result);

        foreach ($result as $key => $chunks) {
            self::assertIsString($key);
            self::assertIsArray($chunks);

            foreach ($chunks as $chunk) {
                self::assertIsArray($chunk);
                self::assertCount(1, $chunk);

                foreach ($chunk as $entry) {
                    self::assertArrayHasKey('ext', $entry);
                    self::assertArrayHasKey('class', $entry);
                }
            }
        }
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function index_echoes_file_list_as_json(): void
    {
        // Response::json() calls send(), which echoes the payload to stdout and
        // pollutes the test-runner output. Swallow that echo; the body is still
        // recorded on the Response object, so the assertions below are unaffected.
        ob_start();
        try {
            $this->controller->index();
        } finally {
            ob_end_clean();
        }

        $body    = (string)$this->response->body();
        $decoded = json_decode($body, true);

        self::assertIsArray($decoded);
        self::assertNotEmpty($decoded);
        self::assertSame(json_encode($this->buildExpectedFileList()), json_encode($decoded));
    }
}
