<?php

namespace Tests\Unit\Controllers;

use Controller\API\App\AIAssistantController;
use Exception;
use InvalidArgumentException;
use Klein\Request;
use Klein\Response;
use Matecat\Locales\InvalidLanguageException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;

#[Group('unit')]
class AIAssistantControllerTest extends AbstractTest
{
    private string $openAiApiKeyBackup;
    private string $geminiApiKeyBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openAiApiKeyBackup = AppConfig::$OPENAI_API_KEY;
        $this->geminiApiKeyBackup = AppConfig::$GEMINI_API_KEY;
    }

    protected function tearDown(): void
    {
        AppConfig::$OPENAI_API_KEY = $this->openAiApiKeyBackup;
        AppConfig::$GEMINI_API_KEY = $this->geminiApiKeyBackup;
        parent::tearDown();
    }

    /**
     * @throws ReflectionException
     */
    private function makeControllerWithBody(string $body): AIAssistantController
    {
        $request = $this->createStub(Request::class);
        $request->method('body')->willReturn($body);

        $response = $this->createStub(Response::class);

        $reflection = new ReflectionClass(AIAssistantController::class);
        /** @var AIAssistantController $controller */
        $controller = $reflection->newInstanceWithoutConstructor();

        $requestProperty = new ReflectionProperty($controller, 'request');
        $requestProperty->setValue($controller, $request);

        $responseProperty = new ReflectionProperty($controller, 'response');
        $responseProperty->setValue($controller, $response);

        return $controller;
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function indexThrowsForInvalidJsonBody(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $controller = $this->makeControllerWithBody('not-json');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON body');

        $controller->index();
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function indexThrowsForMissingTargetParameter(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $controller = $this->makeControllerWithBody('{"id_segment":1,"word":"x","phrase":"y","id_client":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `target` parameter');

        $controller->index();
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function indexThrowsForInvalidTargetLanguage(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $controller = $this->makeControllerWithBody('{"target":"zz_invalid","id_segment":1,"word":"x","phrase":"y","id_client":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidLanguageException::class);

        $controller->index();
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function indexThrowsWhenOpenAiApiKeyIsMissing(): void
    {
        AppConfig::$OPENAI_API_KEY = '';
        $controller = $this->makeControllerWithBody('{}');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('OpenAI API key not set');

        $controller->index();
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function feedbackThrowsForInvalidJsonBody(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $controller = $this->makeControllerWithBody('not-json');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON body');

        $controller->feedback();
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function feedbackThrowsForMissingSourceLanguageParameter(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $controller = $this->makeControllerWithBody('{"target_language":"it","text":"hello","translation":"ciao","style":"neutral","id_client":1,"id_segment":1}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `source_language` parameter');

        $controller->feedback();
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function feedbackThrowsForInvalidSourceLanguage(): void
    {
        AppConfig::$OPENAI_API_KEY = 'test-openai-key';
        $controller = $this->makeControllerWithBody('{"source_language":"zz_invalid","target_language":"it","text":"hello","translation":"ciao","style":"neutral","id_client":1,"id_segment":1}');

        $this->expectException(InvalidLanguageException::class);

        $controller->feedback();
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function feedbackThrowsWhenOpenAiApiKeyIsMissing(): void
    {
        AppConfig::$OPENAI_API_KEY = '';
        $controller = $this->makeControllerWithBody('{}');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('OpenAI API key not set');

        $controller->feedback();
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function alternativeTranslationsThrowsForInvalidJsonBody(): void
    {
        AppConfig::$GEMINI_API_KEY = 'test-gemini-key';
        $controller = $this->makeControllerWithBody('not-json');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JSON body');

        $controller->alternative_translations();
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function alternativeTranslationsThrowsForMissingSourceLanguageParameter(): void
    {
        AppConfig::$GEMINI_API_KEY = 'test-gemini-key';
        $controller = $this->makeControllerWithBody('{"target_language":"it","source_sentence":"a","target_sentence":"b","source_context_sentences_string":"ctx","target_context_sentences_string":"ctx","excerpt":"ex","style_instructions":"neutral","id_client":1,"id_segment":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing `source_language` parameter');

        $controller->alternative_translations();
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function alternativeTranslationsThrowsForInvalidSourceLanguage(): void
    {
        AppConfig::$GEMINI_API_KEY = 'test-gemini-key';
        $controller = $this->makeControllerWithBody('{"source_language":"zz_invalid","target_language":"it","source_sentence":"a","target_sentence":"b","source_context_sentences_string":"ctx","target_context_sentences_string":"ctx","excerpt":"ex","style_instructions":"neutral","id_client":1,"id_segment":1,"id_job":1,"password":"p"}');

        $this->expectException(InvalidLanguageException::class);

        $controller->alternative_translations();
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function alternativeTranslationsThrowsWhenGeminiApiKeyIsMissing(): void
    {
        AppConfig::$GEMINI_API_KEY = '';
        $controller = $this->makeControllerWithBody('{}');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Gemini API key not set');

        $controller->alternative_translations();
    }
}
