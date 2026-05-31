<?php

namespace unit\Workers;

use Exception;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use Predis\Client;
use TestHelpers\AbstractTest;
use Utils\ActiveMQ\AMQHandler;
use Utils\AIAssistant\GeminiClient;
use Utils\AIAssistant\OpenAIClient;
use Utils\AsyncTasks\Workers\AIAssistantWorker;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;

class AIAssistantWorkerTest extends AbstractTest
{
    private Client $redisMock;

    protected function setUp(): void
    {
        parent::setUp();
        AppConfig::$SKIP_SQL_CACHE = true;
        $this->createDatabaseMock();
        $this->redisMock = $this->createStub(Client::class);
    }

    protected function tearDown(): void
    {
        $this->resetDatabaseMock();
        AppConfig::$SKIP_SQL_CACHE = false;
        parent::tearDown();
    }

    private function createWorker(?GeminiClient $gemini = null, ?OpenAIClient $openAi = null): AIAssistantWorker
    {
        $amq = $this->createStub(AMQHandler::class);

        $worker = $this->getMockBuilder(AIAssistantWorker::class)
            ->setConstructorArgs([$amq, $this->redisMock])
            ->onlyMethods(['_checkDatabaseConnection', '_doLog', 'publishToNodeJsClients', 'createAIClient'])
            ->getMock();

        if ($gemini || $openAi) {
            $worker->method('createAIClient')->willReturnCallback(function (string $agent) use ($gemini, $openAi) {
                return $agent === 'gemini' ? $gemini : $openAi;
            });
        }

        return $worker;
    }

    private function createQueueElement(string $action, array $payload): QueueElement
    {
        $params = new Params();
        $params->action = $action;
        $params->payload = $payload;

        $queueElement = new QueueElement();
        $queueElement->params = $params;
        $queueElement->reQueueNum = 0;

        return $queueElement;
    }

    // ─── process() ───

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function processThrowsOnInvalidAction(): void
    {
        $worker = $this->createWorker();

        $this->expectException(EndQueueException::class);
        $worker->process($this->createQueueElement('invalid_action', []));
    }

    #[Test]
    public function processCallsAlternativeTranslations(): void
    {
        $gemini = $this->createStub(GeminiClient::class);
        $gemini->method('manageAlternativeTranslations')->willReturn(['translation1', 'translation2']);

        $worker = $this->createWorker(gemini: $gemini);
        $worker->expects($this->atLeastOnce())->method('publishToNodeJsClients');

        $payload = [
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'source_sentence' => 'Hello',
            'target_sentence' => 'Ciao',
            'source_context_sentences_string' => '',
            'target_context_sentences_string' => '',
            'excerpt' => '',
            'style_instructions' => '',
            'id_segment' => '1',
            'id_client' => 'client1',
        ];

        $worker->process($this->createQueueElement('alternative_translations', $payload));
    }

    #[Test]
    public function processCallsFeedback(): void
    {
        $openAi = $this->createStub(OpenAIClient::class);
        $openAi->method('evaluateTranslation')->willReturn(['feedback' => 'Good translation']);

        $worker = $this->createWorker(openAi: $openAi);
        $worker->expects($this->atLeastOnce())->method('publishToNodeJsClients');

        $payload = [
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'text' => 'Hello',
            'translation' => 'Ciao',
            'style' => 'formal',
            'id_client' => 'client1',
            'id_segment' => '1',
        ];

        $worker->process($this->createQueueElement('feedback', $payload));
    }

    // ─── alternative_translations error paths ───

    #[Test]
    public function alternativeTranslationsEmitsErrorOnEmpty(): void
    {
        $gemini = $this->createStub(GeminiClient::class);
        $gemini->method('manageAlternativeTranslations')->willReturn([]);

        $worker = $this->createWorker(gemini: $gemini);
        $worker->expects($this->atLeastOnce())
            ->method('publishToNodeJsClients')
            ->with($this->callback(function (array $data) {
                return $data['data']['payload']['has_error'] === true
                    && $data['data']['payload']['error_code'] === 1;
            }));

        $payload = [
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'source_sentence' => 'Hello',
            'target_sentence' => 'Ciao',
            'source_context_sentences_string' => '',
            'target_context_sentences_string' => '',
            'excerpt' => '',
            'style_instructions' => '',
            'id_segment' => '1',
            'id_client' => 'client1',
        ];

        $worker->process($this->createQueueElement('alternative_translations', $payload));
    }

    #[Test]
    public function alternativeTranslationsEmitsErrorOnException(): void
    {
        $gemini = $this->createStub(GeminiClient::class);
        $gemini->method('manageAlternativeTranslations')->willThrowException(new Exception('API error'));

        $worker = $this->createWorker(gemini: $gemini);
        $worker->expects($this->atLeastOnce())
            ->method('publishToNodeJsClients')
            ->with($this->callback(function (array $data) {
                return $data['data']['payload']['has_error'] === true
                    && $data['data']['payload']['error_code'] === 2;
            }));

        $payload = [
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'source_sentence' => 'Hello',
            'target_sentence' => 'Ciao',
            'source_context_sentences_string' => '',
            'target_context_sentences_string' => '',
            'excerpt' => '',
            'style_instructions' => '',
            'id_segment' => '1',
            'id_client' => 'client1',
        ];

        $worker->process($this->createQueueElement('alternative_translations', $payload));
    }

    // ─── feedback error path ───

    #[Test]
    public function feedbackEmitsErrorOnException(): void
    {
        $openAi = $this->createStub(OpenAIClient::class);
        $openAi->method('evaluateTranslation')->willThrowException(new Exception('API error'));

        $worker = $this->createWorker(openAi: $openAi);
        $worker->expects($this->atLeastOnce())
            ->method('publishToNodeJsClients')
            ->with($this->callback(function (array $data) {
                return $data['data']['payload']['has_error'] === true;
            }));

        $payload = [
            'localized_source' => 'English',
            'localized_target' => 'Italian',
            'text' => 'Hello',
            'translation' => 'Ciao',
            'style' => 'formal',
            'id_client' => 'client1',
            'id_segment' => '1',
        ];

        $worker->process($this->createQueueElement('feedback', $payload));
    }

    // ─── explain_meaning ───

    private function createLockTrackingRedisMock(): void
    {
        $storedLock = null;
        $this->redisMock->method('__call')->willReturnCallback(function ($method, $args) use (&$storedLock) {
            if ($method === 'set') {
                $storedLock = $args[1] ?? $args[0];
                return true;
            }
            if ($method === 'get') {
                return $storedLock;
            }
            if ($method === 'del') {
                $storedLock = null;
                return 1;
            }
            return true;
        });
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function explainMeaningProcessesStreamedData(): void
    {
        $openAi = $this->createStub(OpenAIClient::class);
        $openAi->method('findContextForAWord')
            ->willReturnCallback(function ($word, $phrase, $target, callable $callback) {
                $sseChunk = 'data: {"choices":[{"delta":{"content":"Hello"}}]}' . "\n\n";
                $callback(null, $sseChunk);

                $sseDone = "data: [DONE]\n\n";
                $callback(null, $sseDone);
            });

        $this->createLockTrackingRedisMock();

        $worker = $this->createWorker(openAi: $openAi);

        $payload = [
            'phrase' => 'Hello world this is a test',
            'word' => 'Hello',
            'localized_target' => 'Italian',
            'id_segment' => '1',
            'id_job' => 10,
            'password' => 'abc',
            'id_client' => 'client1',
        ];

        AppConfig::$OPEN_AI_MAX_TOKENS = 100;

        $worker->process($this->createQueueElement('explain_meaning', $payload));
        $this->assertTrue(true);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function explainMeaningHandlesLockMismatch(): void
    {
        $lockCallCount = 0;
        $openAi = $this->createStub(OpenAIClient::class);
        $openAi->method('findContextForAWord')
            ->willReturnCallback(function ($word, $phrase, $target, callable $callback) {
                $sseChunk = 'data: {"choices":[{"delta":{"content":"Hi"}}]}' . "\n\n";
                $callback(null, $sseChunk);
            });

        $this->redisMock->method('__call')->willReturnCallback(function ($method, $args) use (&$lockCallCount) {
            if ($method === 'get') {
                return 'different-lock-value';
            }
            return true;
        });

        $worker = $this->createWorker(openAi: $openAi);

        $payload = [
            'phrase' => 'Hello world',
            'word' => 'Hello',
            'localized_target' => 'Italian',
            'id_segment' => '1',
            'id_job' => 10,
            'password' => 'abc',
            'id_client' => 'client1',
        ];

        AppConfig::$OPEN_AI_MAX_TOKENS = 100;

        $worker->process($this->createQueueElement('explain_meaning', $payload));
        $this->assertTrue(true);
    }

    #[Test]
    public function explainMeaningHandlesOpenAiError(): void
    {
        $openAi = $this->createStub(OpenAIClient::class);
        $openAi->method('findContextForAWord')
            ->willReturnCallback(function ($word, $phrase, $target, callable $callback) {
                $sseError = 'data: {"error":{"message":"Rate limit exceeded"}}' . "\n\n";
                $callback(null, $sseError);
            });

        $this->createLockTrackingRedisMock();

        $worker = $this->createWorker(openAi: $openAi);
        $worker->expects($this->atLeastOnce())->method('publishToNodeJsClients');

        $payload = [
            'phrase' => 'Hello world',
            'word' => 'Hello',
            'localized_target' => 'Italian',
            'id_segment' => '1',
            'id_job' => 10,
            'password' => 'abc',
            'id_client' => 'client1',
        ];

        AppConfig::$OPEN_AI_MAX_TOKENS = 100;

        $worker->process($this->createQueueElement('explain_meaning', $payload));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function explainMeaningHandlesInvalidJson(): void
    {
        $openAi = $this->createStub(OpenAIClient::class);
        $openAi->method('findContextForAWord')
            ->willReturnCallback(function ($word, $phrase, $target, callable $callback) {
                $sseInvalid = "data: {invalid json}\n\n";
                $callback(null, $sseInvalid);

                $sseDone = "data: [DONE]\n\n";
                $callback(null, $sseDone);
            });

        $this->createLockTrackingRedisMock();

        $worker = $this->createWorker(openAi: $openAi);

        $payload = [
            'phrase' => 'Hello world',
            'word' => 'Hello',
            'localized_target' => 'Italian',
            'id_segment' => '1',
            'id_job' => 10,
            'password' => 'abc',
            'id_client' => 'client1',
        ];

        AppConfig::$OPEN_AI_MAX_TOKENS = 100;

        $worker->process($this->createQueueElement('explain_meaning', $payload));
        $this->assertTrue(true);
    }

    // ─── constants ───

    #[Test]
    public function codeErrorsMapHasExpectedKeys(): void
    {
        $this->assertSame(0, AIAssistantWorker::codeErrorsMap['NO_ERROR']);
        $this->assertSame(1, AIAssistantWorker::codeErrorsMap['NO_ALTERNATIVE_TRANSLATIONS_FOUND']);
        $this->assertSame(2, AIAssistantWorker::codeErrorsMap['ERROR_GENERATING_ALTERNATIVE_TRANSLATIONS']);
        $this->assertSame(3, AIAssistantWorker::codeErrorsMap['NO_ERROR_MESSAGE']);
        $this->assertSame(4, AIAssistantWorker::codeErrorsMap['OTHER_ERROR']);
    }

    #[Test]
    public function actionConstantsAreDefined(): void
    {
        $this->assertSame('explain_meaning', AIAssistantWorker::EXPLAIN_MEANING_ACTION);
        $this->assertSame('feedback', AIAssistantWorker::FEEDBACK_ACTION);
        $this->assertSame('alternative_translations', AIAssistantWorker::ALTERNATIVE_TRANSLATIONS_ACTION);
    }
}
