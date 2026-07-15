<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\SupportedLanguagesController;
use Klein\Request;
use Klein\Response;
use Matecat\Locales\Languages;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;

/**
 * SupportedLanguagesController test (App coverage campaign, Wave 1).
 *
 * No DB access, no auth, no external services: the controller only reads the
 * process-local Languages::getInstance() singleton and echoes its enabled
 * language list as JSON. No DB-ID block needed.
 */
class TestableSupportedLanguagesController extends SupportedLanguagesController
{
    public function __construct()
    {
    }
}

class SupportedLanguagesControllerTest extends AbstractTest
{
    private TestableSupportedLanguagesController $controller;
    private Response $response;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new TestableSupportedLanguagesController();
        $this->response   = new Response();
        $this->setProp('request', new Request([], [], [], ['REQUEST_URI' => '/api/app/supported_langs', 'REQUEST_METHOD' => 'GET']));
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
