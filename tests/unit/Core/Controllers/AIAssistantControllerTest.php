<?php

namespace Matecat\Core\Controllers;

use Controller\API\App\AIAssistantController;
use Exception;
use InvalidArgumentException;
use Klein\HttpStatus;
use Klein\Request;
use Klein\Response;
use Matecat\Locales\InvalidLanguageException;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use Utils\Registry\AppConfig;

/**
 * Testable subclass exposing an empty constructor and neutralising the
 * ActiveMQ enqueue side-effect so the controller can run to completion in a
 * unit test without touching the external message broker.
 *
 * @see AIAssistantController::enqueueWorker() (promoted to protected as the seam)
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

#[Group('unit')]
#[AllowMockObjectsWithoutExpectations]
class AIAssistantControllerTest extends AbstractTest
{
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

        $this->reflector  = new ReflectionClass(AIAssistantController::class);
        $this->controller = new TestableAIAssistantController();
    }

    protected function tearDown(): void
    {
        AppConfig::$OPENAI_API_KEY = $this->openAiApiKeyBackup;
        AppConfig::$GEMINI_API_KEY = $this->geminiApiKeyBackup;
        parent::tearDown();
    }

    private function setProp(string $name, mixed $value): void
    {
        $prop = $this->reflector->getProperty($name);
        $prop->setValue($this->controller, $value);
    }

    /**
     * Inject a request whose body() returns the given raw string.
     */
    private function injectBody(string $body): void
    {
        $request = $this->createStub(Request::class);
        $request->method('body')->willReturn($body);
        $this->setProp('request', $request);
    }

    /**
     * Inject a Response mock that captures the emitted JSON payload and the
     * status code, without performing any real output/send.
     *
     * @param array<string, mixed> $captured filled by reference with keys 'json' and 'code'
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

    // ---------------------------------------------------------------------
    // index()
    // ---------------------------------------------------------------------

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
    public function indexThrowsForInvalidJsonBody(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('not-json');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON body');

        $this->controller->index();
    }

    #[Test]
    public function indexThrowsForMissingTargetParameter(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('{"id_segment":1,"word":"x","phrase":"y","id_client":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `target` parameter');

        $this->controller->index();
    }

    #[Test]
    public function indexThrowsForInvalidTargetLanguage(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('{"target":"zz_invalid","id_segment":1,"word":"x","phrase":"y","id_client":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidLanguageException::class);

        $this->controller->index();
    }

    #[Test]
    public function indexThrowsForMissingIdSegmentParameter(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('{"target":"it-IT","word":"x","phrase":"y","id_client":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `id_segment` parameter');

        $this->controller->index();
    }

    #[Test]
    public function indexThrowsForMissingWordParameter(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('{"target":"it-IT","id_segment":1,"phrase":"y","id_client":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `word` parameter');

        $this->controller->index();
    }

    #[Test]
    public function indexThrowsForMissingPhraseParameter(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('{"target":"it-IT","id_segment":1,"word":"x","id_client":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `phrase` parameter');

        $this->controller->index();
    }

    #[Test]
    public function indexThrowsForMissingIdClientParameter(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('{"target":"it-IT","id_segment":1,"word":"x","phrase":"y","id_job":1,"password":"p"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `id_client` parameter');

        $this->controller->index();
    }

    #[Test]
    public function indexThrowsForMissingIdJobParameter(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('{"target":"it-IT","id_segment":1,"word":"x","phrase":"y","id_client":1,"password":"p"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `id_job` parameter');

        $this->controller->index();
    }

    #[Test]
    public function indexThrowsForMissingPasswordParameter(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('{"target":"it-IT","id_segment":1,"word":"x","phrase":"y","id_client":1,"id_job":1}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `password` parameter');

        $this->controller->index();
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function indexEnqueuesAndRespondsOnHappyPath(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('{"target":"it-IT","id_segment":10,"word":"  hello  ","phrase":"  a phrase  ","id_client":"cid","id_job":20,"password":"pw"}');

        $captured = [];
        $this->injectResponse($captured);

        $this->controller->index();

        self::assertCount(1, $this->controller->enqueuedParams);
        $payload = $this->controller->enqueuedParams[0]['payload'];
        self::assertSame('hello', $payload['word']);
        self::assertSame('a phrase', $payload['phrase']);
        self::assertSame('cid', $payload['id_client']);
        self::assertSame(20, $payload['id_job']);
        self::assertSame('pw', $payload['password']);
        self::assertNotEmpty($payload['localized_target']);

        self::assertSame(200, $captured['code']);
        self::assertSame($payload, $captured['json']);
    }

    // ---------------------------------------------------------------------
    // feedback()
    // ---------------------------------------------------------------------

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
    public function feedbackThrowsForInvalidJsonBody(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('not-json');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON body');

        $this->controller->feedback();
    }

    #[Test]
    public function feedbackThrowsForMissingSourceLanguageParameter(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('{"target_language":"it-IT","text":"hello","translation":"ciao","style":"faithful","id_client":1,"id_segment":1}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `source_language` parameter');

        $this->controller->feedback();
    }

    #[Test]
    public function feedbackThrowsForMissingTargetLanguageParameter(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('{"source_language":"en-US","text":"hello","translation":"ciao","style":"faithful","id_client":1,"id_segment":1}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `target_language` parameter');

        $this->controller->feedback();
    }

    #[Test]
    public function feedbackThrowsForInvalidSourceLanguage(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('{"source_language":"zz_invalid","target_language":"it-IT","text":"hello","translation":"ciao","style":"faithful","id_client":1,"id_segment":1}');

        $this->expectException(InvalidLanguageException::class);

        $this->controller->feedback();
    }

    #[Test]
    public function feedbackThrowsForInvalidTargetLanguage(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('{"source_language":"en-US","target_language":"zz_invalid","text":"hello","translation":"ciao","style":"faithful","id_client":1,"id_segment":1}');

        $this->expectException(InvalidLanguageException::class);

        $this->controller->feedback();
    }

    #[Test]
    public function feedbackThrowsForMissingTextParameter(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('{"source_language":"en-US","target_language":"it-IT","translation":"ciao","style":"faithful","id_client":1,"id_segment":1}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `text` parameter');

        $this->controller->feedback();
    }

    #[Test]
    public function feedbackThrowsForMissingTranslationParameter(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('{"source_language":"en-US","target_language":"it-IT","text":"hello","style":"faithful","id_client":1,"id_segment":1}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `translation` parameter');

        $this->controller->feedback();
    }

    #[Test]
    public function feedbackThrowsForMissingStyleParameter(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('{"source_language":"en-US","target_language":"it-IT","text":"hello","translation":"ciao","id_client":1,"id_segment":1}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `style` parameter');

        $this->controller->feedback();
    }

    #[Test]
    public function feedbackThrowsForInvalidStyle(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('{"source_language":"en-US","target_language":"it-IT","text":"hello","translation":"ciao","style":"not-a-style","id_client":1,"id_segment":1}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Lara style.');

        $this->controller->feedback();
    }

    #[Test]
    public function feedbackThrowsForMissingIdClientParameter(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('{"source_language":"en-US","target_language":"it-IT","text":"hello","translation":"ciao","style":"faithful","id_segment":1}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `id_client` parameter');

        $this->controller->feedback();
    }

    #[Test]
    public function feedbackThrowsForMissingIdSegmentParameter(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('{"source_language":"en-US","target_language":"it-IT","text":"hello","translation":"ciao","style":"faithful","id_client":1}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `id_segment` parameter');

        $this->controller->feedback();
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function feedbackEnqueuesAndRespondsOnHappyPath(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $this->injectBody('{"source_language":"en-US","target_language":"it-IT","text":"  hi  ","translation":"  ciao  ","style":"faithful","id_client":"cid","id_segment":30}');

        $captured = [];
        $this->injectResponse($captured);

        $this->controller->feedback();

        self::assertCount(1, $this->controller->enqueuedParams);
        $payload = $this->controller->enqueuedParams[0]['payload'];
        self::assertSame('hi', $payload['text']);
        self::assertSame('ciao', $payload['translation']);
        self::assertSame('faithful', $payload['style']);
        self::assertSame('cid', $payload['id_client']);
        self::assertSame(30, $payload['id_segment']);
        self::assertNotEmpty($payload['localized_source']);
        self::assertNotEmpty($payload['localized_target']);

        self::assertSame(200, $captured['code']);
        self::assertSame($payload, $captured['json']);
    }

    // ---------------------------------------------------------------------
    // alternative_translations()
    // ---------------------------------------------------------------------

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
    public function alternativeTranslationsThrowsForInvalidJsonBody(): void
    {
        AppConfig::$GEMINI_API_KEY = 'test-gemini-key';
        $this->injectBody('not-json');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON body');

        $this->controller->alternative_translations();
    }

    #[Test]
    public function alternativeTranslationsThrowsForMissingSourceLanguageParameter(): void
    {
        AppConfig::$GEMINI_API_KEY = 'test-gemini-key';
        $this->injectBody('{"target_language":"it-IT","source_sentence":"a","target_sentence":"b","source_context_sentences_string":"ctx","target_context_sentences_string":"ctx","excerpt":"ex","style_instructions":"faithful","id_client":1,"id_segment":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `source_language` parameter');

        $this->controller->alternative_translations();
    }

    #[Test]
    public function alternativeTranslationsThrowsForMissingTargetLanguageParameter(): void
    {
        AppConfig::$GEMINI_API_KEY = 'test-gemini-key';
        $this->injectBody('{"source_language":"en-US","source_sentence":"a","target_sentence":"b","source_context_sentences_string":"ctx","target_context_sentences_string":"ctx","excerpt":"ex","style_instructions":"faithful","id_client":1,"id_segment":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `target_language` parameter');

        $this->controller->alternative_translations();
    }

    #[Test]
    public function alternativeTranslationsThrowsForInvalidSourceLanguage(): void
    {
        AppConfig::$GEMINI_API_KEY = 'test-gemini-key';
        $this->injectBody('{"source_language":"zz_invalid","target_language":"it-IT","source_sentence":"a","target_sentence":"b","source_context_sentences_string":"ctx","target_context_sentences_string":"ctx","excerpt":"ex","style_instructions":"faithful","id_client":1,"id_segment":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidLanguageException::class);

        $this->controller->alternative_translations();
    }

    #[Test]
    public function alternativeTranslationsThrowsForInvalidTargetLanguage(): void
    {
        AppConfig::$GEMINI_API_KEY = 'test-gemini-key';
        $this->injectBody('{"source_language":"en-US","target_language":"zz_invalid","source_sentence":"a","target_sentence":"b","source_context_sentences_string":"ctx","target_context_sentences_string":"ctx","excerpt":"ex","style_instructions":"faithful","id_client":1,"id_segment":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidLanguageException::class);

        $this->controller->alternative_translations();
    }

    #[Test]
    public function alternativeTranslationsThrowsForMissingSourceSentenceParameter(): void
    {
        AppConfig::$GEMINI_API_KEY = 'test-gemini-key';
        $this->injectBody('{"source_language":"en-US","target_language":"it-IT","target_sentence":"b","source_context_sentences_string":"ctx","target_context_sentences_string":"ctx","excerpt":"ex","style_instructions":"faithful","id_client":1,"id_segment":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `source_sentence` parameter');

        $this->controller->alternative_translations();
    }

    #[Test]
    public function alternativeTranslationsThrowsForMissingTargetSentenceParameter(): void
    {
        AppConfig::$GEMINI_API_KEY = 'test-gemini-key';
        $this->injectBody('{"source_language":"en-US","target_language":"it-IT","source_sentence":"a","source_context_sentences_string":"ctx","target_context_sentences_string":"ctx","excerpt":"ex","style_instructions":"faithful","id_client":1,"id_segment":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `target_sentence` parameter');

        $this->controller->alternative_translations();
    }

    #[Test]
    public function alternativeTranslationsThrowsForMissingSourceContextParameter(): void
    {
        AppConfig::$GEMINI_API_KEY = 'test-gemini-key';
        $this->injectBody('{"source_language":"en-US","target_language":"it-IT","source_sentence":"a","target_sentence":"b","target_context_sentences_string":"ctx","excerpt":"ex","style_instructions":"faithful","id_client":1,"id_segment":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `source_context_sentences_string` parameter');

        $this->controller->alternative_translations();
    }

    #[Test]
    public function alternativeTranslationsThrowsForMissingTargetContextParameter(): void
    {
        AppConfig::$GEMINI_API_KEY = 'test-gemini-key';
        $this->injectBody('{"source_language":"en-US","target_language":"it-IT","source_sentence":"a","target_sentence":"b","source_context_sentences_string":"ctx","excerpt":"ex","style_instructions":"faithful","id_client":1,"id_segment":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `target_context_sentences_string` parameter');

        $this->controller->alternative_translations();
    }

    #[Test]
    public function alternativeTranslationsThrowsForMissingExcerptParameter(): void
    {
        AppConfig::$GEMINI_API_KEY = 'test-gemini-key';
        $this->injectBody('{"source_language":"en-US","target_language":"it-IT","source_sentence":"a","target_sentence":"b","source_context_sentences_string":"ctx","target_context_sentences_string":"ctx","style_instructions":"faithful","id_client":1,"id_segment":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `excerpt` parameter');

        $this->controller->alternative_translations();
    }

    #[Test]
    public function alternativeTranslationsThrowsForMissingStyleInstructionsParameter(): void
    {
        AppConfig::$GEMINI_API_KEY = 'test-gemini-key';
        $this->injectBody('{"source_language":"en-US","target_language":"it-IT","source_sentence":"a","target_sentence":"b","source_context_sentences_string":"ctx","target_context_sentences_string":"ctx","excerpt":"ex","id_client":1,"id_segment":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `style_instructions` parameter');

        $this->controller->alternative_translations();
    }

    #[Test]
    public function alternativeTranslationsThrowsForInvalidStyleInstructions(): void
    {
        AppConfig::$GEMINI_API_KEY = 'test-gemini-key';
        $this->injectBody('{"source_language":"en-US","target_language":"it-IT","source_sentence":"a","target_sentence":"b","source_context_sentences_string":"ctx","target_context_sentences_string":"ctx","excerpt":"ex","style_instructions":"not-a-style","id_client":1,"id_segment":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Lara style.');

        $this->controller->alternative_translations();
    }

    #[Test]
    public function alternativeTranslationsThrowsForMissingIdClientParameter(): void
    {
        AppConfig::$GEMINI_API_KEY = 'test-gemini-key';
        $this->injectBody('{"source_language":"en-US","target_language":"it-IT","source_sentence":"a","target_sentence":"b","source_context_sentences_string":"ctx","target_context_sentences_string":"ctx","excerpt":"ex","style_instructions":"faithful","id_segment":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `id_client` parameter');

        $this->controller->alternative_translations();
    }

    #[Test]
    public function alternativeTranslationsThrowsForMissingIdSegmentParameter(): void
    {
        AppConfig::$GEMINI_API_KEY = 'test-gemini-key';
        $this->injectBody('{"source_language":"en-US","target_language":"it-IT","source_sentence":"a","target_sentence":"b","source_context_sentences_string":"ctx","target_context_sentences_string":"ctx","excerpt":"ex","style_instructions":"faithful","id_client":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `id_segment` parameter');

        $this->controller->alternative_translations();
    }

    #[Test]
    public function alternativeTranslationsThrowsForMissingIdJobParameter(): void
    {
        AppConfig::$GEMINI_API_KEY = 'test-gemini-key';
        $this->injectBody('{"source_language":"en-US","target_language":"it-IT","source_sentence":"a","target_sentence":"b","source_context_sentences_string":"ctx","target_context_sentences_string":"ctx","excerpt":"ex","style_instructions":"faithful","id_client":1,"id_segment":1,"password":"p"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `id_job` parameter');

        $this->controller->alternative_translations();
    }

    #[Test]
    public function alternativeTranslationsThrowsForMissingPasswordParameter(): void
    {
        AppConfig::$GEMINI_API_KEY = 'test-gemini-key';
        $this->injectBody('{"source_language":"en-US","target_language":"it-IT","source_sentence":"a","target_sentence":"b","source_context_sentences_string":"ctx","target_context_sentences_string":"ctx","excerpt":"ex","style_instructions":"faithful","id_client":1,"id_segment":1,"id_job":1}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `password` parameter');

        $this->controller->alternative_translations();
    }

    /**
     * @throws Exception
     * @throws ReflectionException
     */
    #[Test]
    public function alternativeTranslationsEnqueuesAndRespondsOnHappyPath(): void
    {
        AppConfig::$GEMINI_API_KEY = 'test-gemini-key';
        $this->injectBody('{"source_language":"en-US","target_language":"it-IT","source_sentence":"a","target_sentence":"b","source_context_sentences_string":"sctx","target_context_sentences_string":"tctx","excerpt":"ex","style_instructions":"faithful","id_client":"cid","id_segment":40,"id_job":50,"password":"pw"}');

        $captured = [];
        $this->injectResponse($captured);

        $this->controller->alternative_translations();

        self::assertCount(1, $this->controller->enqueuedParams);
        $payload = $this->controller->enqueuedParams[0]['payload'];
        self::assertSame('cid', $payload['id_client']);
        self::assertSame(50, $payload['id_job']);
        self::assertSame('pw', $payload['password']);
        self::assertSame('a', $payload['source_sentence']);
        self::assertSame('b', $payload['target_sentence']);
        self::assertSame('sctx', $payload['source_context_sentences_string']);
        self::assertSame('tctx', $payload['target_context_sentences_string']);
        self::assertSame('ex', $payload['excerpt']);
        self::assertSame('faithful', $payload['style_instructions']);
        self::assertSame(40, $payload['id_segment']);
        self::assertNotEmpty($payload['localized_source']);
        self::assertNotEmpty($payload['localized_target']);

        self::assertSame(200, $captured['code']);
        self::assertSame($payload, $captured['json']);
    }
}
