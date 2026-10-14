<?php

namespace Matecat\Utils\AIAssistant;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Orhanerday\OpenAi\OpenAi;
use PHPUnit\Framework\Attributes\Test;
use Utils\AIAssistant\OpenAIClient;
use Utils\Registry\AppConfig;

class OpenAIClientTest extends AbstractTest
{
    private string $openAiMaxTokens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openAiMaxTokens = AppConfig::$OPEN_AI_MAX_TOKENS;
        AppConfig::$OPEN_AI_MAX_TOKENS = '1000';
    }

    protected function tearDown(): void
    {
        AppConfig::$OPEN_AI_MAX_TOKENS = $this->openAiMaxTokens;
        parent::tearDown();
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function evaluateTranslationDoesNotSendMaxTokens(): void
    {
        $sentOpts = null;

        $openAi = $this->createMock(OpenAi::class);
        $openAi->expects($this->once())
            ->method('chat')
            ->willReturnCallback(function (array $opts) use (&$sentOpts) {
                $sentOpts = $opts;

                return json_encode([
                    'choices' => [
                        ['message' => ['content' => json_encode(['category' => 'Excellent', 'comment' => 'Fine.'])]],
                    ],
                ]);
            });

        $result = (new OpenAIClient($openAi))->evaluateTranslation('en-US', 'it-IT', 'Hello', 'Ciao', 'formal');

        $this->assertSame(['category' => 'Excellent', 'comment' => 'Fine.'], $result);
        $this->assertIsArray($sentOpts);
        $this->assertArrayNotHasKey('max_tokens', $sentOpts);
        $this->assertSame(3000, $sentOpts['max_completion_tokens']);
        $this->assertFalse($sentOpts['stream']);
    }

    /**
     * @throws Exception
     */
    #[Test]
    public function findContextForAWordDoesNotSendMaxTokens(): void
    {
        $sentOpts = null;
        $callback = function () {
        };

        $openAi = $this->createMock(OpenAi::class);
        $openAi->expects($this->once())
            ->method('chat')
            ->with($this->anything(), $callback)
            ->willReturnCallback(function (array $opts) use (&$sentOpts) {
                $sentOpts = $opts;

                return true;
            });

        (new OpenAIClient($openAi))->findContextForAWord('bank', 'The river bank', 'English', $callback);

        $this->assertIsArray($sentOpts);
        $this->assertArrayNotHasKey('max_tokens', $sentOpts);
        $this->assertSame(3000, $sentOpts['max_completion_tokens']);
        $this->assertTrue($sentOpts['stream']);
        $this->assertSame(1.0, $sentOpts['temperature']);
        $this->assertSame(0, $sentOpts['frequency_penalty']);
        $this->assertSame(0, $sentOpts['presence_penalty']);
    }
}
