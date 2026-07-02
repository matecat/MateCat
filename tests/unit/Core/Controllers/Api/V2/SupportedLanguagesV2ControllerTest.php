<?php

namespace Matecat\Core\Controllers\Api\V2;

use Controller\API\V2\SupportedLanguagesController;
use Klein\Request;
use Klein\Response;
use Matecat\Locales\Languages;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;

/**
 * SupportedLanguagesController (API/V2) test.
 *
 * Byte-identical logic to the already-tested twin Controller\API\App\SupportedLanguagesController
 * (see tests/unit/Core/Controllers/SupportedLanguagesControllerTest.php).
 *
 * No DB access, no auth, no external services: the controller only reads the
 * process-local Languages::getInstance() singleton and echoes its enabled
 * language list as JSON. No DB-ID block needed.
 */
class TestableSupportedLanguagesV2Controller extends SupportedLanguagesController
{
    public function __construct()
    {
    }
}

class SupportedLanguagesV2ControllerTest extends AbstractTest
{
    private TestableSupportedLanguagesV2Controller $controller;
    private Response $response;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new TestableSupportedLanguagesV2Controller();
        $this->response   = new Response();
        $this->setProp('request', new Request([], [], [], ['REQUEST_URI' => '/api/v2/supported_langs', 'REQUEST_METHOD' => 'GET']));
        $this->setProp('response', $this->response);
    }

    /**
     * @throws ReflectionException
     */
    private function setProp(string $name, mixed $value): void
    {
        $prop = (new ReflectionClass(SupportedLanguagesController::class))->getProperty($name);
        $prop->setValue($this->controller, $value);
    }

    #[Test]
    public function index_returns_enabled_languages_as_json_array_of_values(): void
    {
        // Response::json() calls send(), which echoes the (~250KB) language payload to stdout and
        // pollutes the test-runner output. Swallow that echo; the body is still recorded on the
        // Response object, so the assertions below are unaffected.
        ob_start();
        try {
            $this->controller->index();
        } finally {
            ob_end_clean();
        }

        $body = (string)$this->response->body();
        $decoded = json_decode($body, true);

        self::assertIsArray($decoded);
        self::assertNotEmpty($decoded);

        $expected = array_values(Languages::getInstance()->getEnabledLanguages());
        self::assertSame(json_encode($expected), json_encode($decoded));

        // response is a plain list (re-indexed), not the original assoc map keyed by bcp47 code
        self::assertSame(array_values($decoded), $decoded);

        foreach ($decoded as $lang) {
            self::assertArrayHasKey('code', $lang);
            self::assertArrayHasKey('name', $lang);
            self::assertArrayHasKey('direction', $lang);
        }
    }
}
