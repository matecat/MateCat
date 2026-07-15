<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\AIAssistantController;
use Controller\Services\RateLimiterService;
use Exception;
use Klein\HttpStatus;
use Klein\Request;
use Klein\Response;
use Matecat\Locales\InvalidLanguageException;
use Matecat\TestHelpers\AbstractTest;
use Model\Exceptions\NotFoundException;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Utils\Registry\AppConfig;
use Utils\Validator\JSONSchema\Errors\JSONValidatorException;

/**
 * Testable subclass exposing an empty constructor and neutralising the
 * ActiveMQ enqueue side-effect so the controller can run to completion in a
 * unit test without touching the external message broker.
 *
 * @see AIAssistantController::enqueueWorker() (protected seam)
 */
class TestableAIAssistantController extends AIAssistantController
{
    public function __construct()
    {
    }

    /** @var array<int, array<string, mixed>> */
    public array $enqueuedParams = [];

    /**
     * @param array<string, mixed> $params
     */
    protected function enqueueWorker(array $params): void
    {
        $this->enqueuedParams[] = $params;
    }
}

/**
 * Real-DB suite. Reserved ID block base = 9_933_000 (1000 ids reserved).
 */
#[Group('unit')]
#[AllowMockObjectsWithoutExpectations]
class AIAssistantControllerTest extends AbstractTest
{
    private const int B = 9_933_000;
    private const int PROJECT_ID = self::B;
    private const int JOB_ID = self::B + 1;
    private const string JOB_PASSWORD = 'aicw9933000pwd';
    private const string EMAIL = 'aictrl_9933000@example.org';

    private string $openAiApiKeyBackup;
    private string $geminiApiKeyBackup;

    /** @var ReflectionClass<AIAssistantController> */
    private ReflectionClass $reflector;
    private TestableAIAssistantController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openAiApiKeyBackup = AppConfig::$OPENAI_API_KEY;
        $this->geminiApiKeyBackup = AppConfig::$GEMINI_API_KEY;
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        AppConfig::$GEMINI_API_KEY = 'test-gemini-key';

        $this->cleanTestData();
        $this->seedTestData();

        $this->reflector  = new ReflectionClass(AIAssistantController::class);
        $this->controller = new TestableAIAssistantController();

        $this->setProp('database', obtainTestDatabase());
        $this->setProp('response', new Response());

        $user = new UserStruct();
        $user->uid = self::B;
        $user->email = self::EMAIL;
        $this->setProp('user', $user);

        $this->setRateLimiter(null);
    }

    protected function tearDown(): void
    {
        AppConfig::$OPENAI_API_KEY = $this->openAiApiKeyBackup;
        AppConfig::$GEMINI_API_KEY = $this->geminiApiKeyBackup;
        $this->cleanTestData();
        parent::tearDown();
    }

    private function seedTestData(): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $conn->exec(
            "INSERT INTO projects (id, password, id_customer, name, create_date, status_analysis)
             VALUES (" . self::PROJECT_ID . ", 'aicwproj', '" . self::EMAIL . "', 'AICtrlProject9933000', NOW(), 'DONE')"
        );
        $conn->exec(
            "INSERT INTO jobs (id, password, id_project, source, target, owner)
             VALUES (" . self::JOB_ID . ", '" . self::JOB_PASSWORD . "', " . self::PROJECT_ID . ", 'en-US', 'it-IT', '" . self::EMAIL . "')"
        );
    }

    private function cleanTestData(): void
    {
        $conn = obtainTestDatabase()->getConnection();
        $conn->exec("DELETE FROM jobs WHERE id = " . self::JOB_ID);
        $conn->exec("DELETE FROM projects WHERE id = " . self::PROJECT_ID);
    }

    private function setProp(string $name, mixed $value): void
    {
        $c = $this->reflector;
        while ($c !== false && !$c->hasProperty($name)) {
            $c = $c->getParentClass();
        }
        $prop = $c->getProperty($name);
        $prop->setValue($this->controller, $value);
    }

    /**
     * Inject a RateLimiterService stub. Passing a Response makes every call
     * report the caller as rate-limited; null lets the request through.
     */
    private function setRateLimiter(?Response $limited): void
    {
        $stub = $this->createStub(RateLimiterService::class);
        $stub->method('checkAndIncrement')->willReturn($limited);
        $this->setProp('rateLimiterService', $stub);
    }

    private function injectBody(string $body): void
    {
        $request = $this->createStub(Request::class);
        $request->method('body')->willReturn($body);
        $this->setProp('request', $request);
    }

    /**
     * @param array<string, mixed> $captured filled by reference with 'json' and 'code'
     */
    private function injectResponse(array &$captured): void
    {
        $captured = ['json' => null, 'code' => null];

        $status = $this->createStub(HttpStatus::class);
        $status->method('setCode')->willReturnCallback(
            static function (int $code) use (&$captured, $status): HttpStatus {
                $captured['code'] = $code;

                return $status;
            }
        );

        $response = $this->createMock(Response::class);
        $response->method('status')->willReturn($status);
        $response->method('json')->willReturnCallback(
            static function (mixed $object) use (&$captured, $response) {
                $captured['json'] = $object;

                return $response;
            }
        );

        $this->setProp('response', $response);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function validIndexBody(array $overrides = []): array
    {
        return array_merge([
            'id_client'  => 'client-1',
            'id_segment' => 1,
            'id_job'     => self::JOB_ID,
            'password'   => self::JOB_PASSWORD,
            'target'     => 'it-IT',
            'word'       => 'ciao',
            'phrase'     => 'ciao mondo',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function validFeedbackBody(array $overrides = []): array
    {
        return array_merge([
            'source_language' => 'en-US',
            'target_language' => 'it-IT',
            'text'            => 'hello',
            'translation'     => 'ciao',
            'style'           => 'faithful',
            'id_client'       => 'client-1',
            'id_segment'      => 1,
            'id_job'          => self::JOB_ID,
            'password'        => self::JOB_PASSWORD,
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function validAltBody(array $overrides = []): array
    {
        return array_merge([
            'source_language'                 => 'en-US',
            'target_language'                 => 'it-IT',
            'source_sentence'                 => 'hello world',
            'target_sentence'                 => 'ciao mondo',
            'source_context_sentences_string' => 'ctx en',
            'target_context_sentences_string' => 'ctx it',
            'excerpt'                         => 'hello',
            'style_instructions'             => 'faithful',
            'id_client'                       => 'client-1',
            'id_segment'                      => 1,
            'id_job'                          => self::JOB_ID,
            'password'                        => self::JOB_PASSWORD,
        ], $overrides);
    }

    // ─── index() ─────────────────────────────────────────────────────────

    #[Test]
    public function indexThrowsWhenOpenAiApiKeyIsMissing(): void
    {
        AppConfig::$OPENAI_API_KEY = '';
        $this->injectBody('{}');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('OpenAI API key not set');

        $this->controller->index();
    }

    #[Test]
    public function indexShortCircuitsWhenRateLimited(): void
    {
        $limited = new Response();
        $limited->code(429);
        $this->setRateLimiter($limited);
        $this->injectBody(json_encode($this->validIndexBody()));

        $this->controller->index();

        $response = $this->reflector->getProperty('response')->getValue($this->controller);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(429, $response->code());
        $this->assertEmpty($this->controller->enqueuedParams);
    }

    #[Test]
    public function indexRejectsBodyMissingRequiredField(): void
    {
        $body = $this->validIndexBody();
        unset($body['id_job']);
        $this->injectBody(json_encode($body));

        $this->expectException(JSONValidatorException::class);

        $this->controller->index();
    }

    #[Test]
    public function indexRejectsWrongJobPassword(): void
    {
        $this->injectBody(json_encode($this->validIndexBody(['password' => 'wrong-password'])));

        $this->expectException(NotFoundException::class);

        $this->controller->index();
    }

    #[Test]
    public function indexRejectsInvalidTargetLanguage(): void
    {
        $captured = [];
        $this->injectResponse($captured);
        $this->injectBody(json_encode($this->validIndexBody(['target' => 'not-a-lang'])));

        $this->expectException(InvalidLanguageException::class);

        $this->controller->index();
    }

    #[Test]
    public function indexEnqueuesExplainMeaningOnValidRequest(): void
    {
        $captured = [];
        $this->injectResponse($captured);
        $this->injectBody(json_encode($this->validIndexBody()));

        $this->controller->index();

        $this->assertSame(200, $captured['code']);
        $this->assertCount(1, $this->controller->enqueuedParams);
        $payload = $this->controller->enqueuedParams[0]['payload'];
        $this->assertSame(self::JOB_ID, $payload['id_job']);
        $this->assertSame(self::JOB_PASSWORD, $payload['password']);
        $this->assertArrayHasKey('localized_target', $payload);
    }

    // ─── feedback() ──────────────────────────────────────────────────────

    #[Test]
    public function feedbackThrowsWhenOpenAiApiKeyIsMissing(): void
    {
        AppConfig::$OPENAI_API_KEY = '';
        $this->injectBody('{}');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('OpenAI API key not set');

        $this->controller->feedback();
    }

    #[Test]
    public function feedbackRejectsBodyMissingJobCredentials(): void
    {
        $body = $this->validFeedbackBody();
        unset($body['password']);
        $this->injectBody(json_encode($body));

        $this->expectException(JSONValidatorException::class);

        $this->controller->feedback();
    }

    #[Test]
    public function feedbackRejectsWrongJobPassword(): void
    {
        $this->injectBody(json_encode($this->validFeedbackBody(['password' => 'wrong-password'])));

        $this->expectException(NotFoundException::class);

        $this->controller->feedback();
    }

    #[Test]
    public function feedbackEnqueuesOnValidRequest(): void
    {
        $captured = [];
        $this->injectResponse($captured);
        $this->injectBody(json_encode($this->validFeedbackBody()));

        $this->controller->feedback();

        $this->assertSame(200, $captured['code']);
        $this->assertCount(1, $this->controller->enqueuedParams);
        $this->assertSame(
            \Utils\AsyncTasks\Workers\AIAssistantWorker::FEEDBACK_ACTION,
            $this->controller->enqueuedParams[0]['action']
        );
    }

    // ─── alternative_translations() ──────────────────────────────────────

    #[Test]
    public function alternativeTranslationsThrowsWhenGeminiApiKeyIsMissing(): void
    {
        AppConfig::$GEMINI_API_KEY = '';
        $this->injectBody('{}');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Gemini API key not set');

        $this->controller->alternative_translations();
    }

    #[Test]
    public function alternativeTranslationsRejectsWrongJobPassword(): void
    {
        $this->injectBody(json_encode($this->validAltBody(['password' => 'wrong-password'])));

        $this->expectException(NotFoundException::class);

        $this->controller->alternative_translations();
    }

    #[Test]
    public function alternativeTranslationsEnqueuesOnValidRequest(): void
    {
        $captured = [];
        $this->injectResponse($captured);
        $this->injectBody(json_encode($this->validAltBody()));

        $this->controller->alternative_translations();

        $this->assertSame(200, $captured['code']);
        $this->assertCount(1, $this->controller->enqueuedParams);
        $payload = $this->controller->enqueuedParams[0]['payload'];
        $this->assertSame(self::JOB_ID, $payload['id_job']);
    }

    #[Test]
    public function feedbackShortCircuitsWhenRateLimited(): void
    {
        $limited = new Response();
        $limited->code(429);
        $this->setRateLimiter($limited);
        $this->injectBody(json_encode($this->validFeedbackBody()));

        $this->controller->feedback();

        $this->assertEmpty($this->controller->enqueuedParams);
    }

    #[Test]
    public function alternativeTranslationsShortCircuitsWhenRateLimited(): void
    {
        $limited = new Response();
        $limited->code(429);
        $this->setRateLimiter($limited);
        $this->injectBody(json_encode($this->validAltBody()));

        $this->controller->alternative_translations();

        $this->assertEmpty($this->controller->enqueuedParams);
    }

    #[Test]
    public function indexRejectsEmptyBody(): void
    {
        $this->injectBody('');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing request body');

        $this->controller->index();
    }

    // ─── lifecycle wiring ────────────────────────────────────────────────

    #[Test]
    public function registerValidatorsAppendsLoginValidator(): void
    {
        $this->injectBody('{}'); // LoginValidator ctor reads the controller's request

        $this->reflector->getMethod('registerValidators')->invoke($this->controller);

        $validators = $this->reflector->getProperty('validators')->getValue($this->controller);
        $this->assertNotEmpty($validators);
        $this->assertInstanceOf(
            \Controller\API\Commons\Validators\LoginValidator::class,
            $validators[0]
        );
    }

    #[Test]
    public function initDependenciesCreatesRateLimiter(): void
    {
        $this->reflector->getMethod('initDependencies')->invoke($this->controller);

        $service = $this->reflector->getProperty('rateLimiterService')->getValue($this->controller);
        $this->assertInstanceOf(RateLimiterService::class, $service);
    }
}
