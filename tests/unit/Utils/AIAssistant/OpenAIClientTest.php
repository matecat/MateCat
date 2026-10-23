<?php

namespace Matecat\Utils\AIAssistant;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Orhanerday\OpenAi\OpenAi;
use PHPUnit\Framework\Attributes\DataProvider;
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
     * Raw OpenAI bodies that carry no usable feedback, each with the reason the client must report.
     *
     * @return array<string, array{string, string}>
     */
    public static function unusableResponses(): array
    {
        return [
            'api error' => [
                json_encode([
                    'error' => [
                        'message' => "Unsupported parameter: 'max_tokens' is not supported with this model.",
                        'type' => 'invalid_request_error',
                        'code' => 'unsupported_parameter',
                    ],
                ]),
                "OpenAI returned an error (unsupported_parameter): Unsupported parameter: 'max_tokens' is not supported with this model.",
            ],
            'api error without code' => [
                json_encode(['error' => ['message' => ['not', 'a', 'string'], 'code' => null]]),
                'OpenAI returned an error (no error code): no error message',
            ],
            'body is not json' => [
                'Bad gateway',
                'OpenAI returned a response that is not valid JSON',
            ],
            'no choices' => [
                json_encode(['choices' => []]),
                'OpenAI returned feedback without a category and a comment',
            ],
            'model refusal' => [
                json_encode([
                    'choices' => [
                        ['message' => ['content' => null, 'refusal' => 'I cannot help with that.']],
                    ],
                ]),
                'OpenAI refused to evaluate the translation: I cannot help with that.',
            ],
            'completion tokens exhausted' => [
                json_encode([
                    'choices' => [
                        ['message' => ['content' => ''], 'finish_reason' => 'length'],
                    ],
                ]),
                'OpenAI ran out of completion tokens before writing the feedback',
            ],
            'empty content' => [
                json_encode([
                    'choices' => [
                        ['message' => ['content' => ''], 'finish_reason' => 'stop'],
                    ],
                ]),
                'OpenAI returned feedback without a category and a comment',
            ],
            'content is not a string' => [
                json_encode([
                    'choices' => [
                        ['message' => ['content' => ['category' => 'Excellent']]],
                    ],
                ]),
                'OpenAI returned feedback without a category and a comment',
            ],
            'category is not a string' => [
                json_encode([
                    'choices' => [
                        ['message' => ['content' => json_encode(['category' => 1, 'comment' => 'Fine.'])]],
                    ],
                ]),
                'OpenAI returned feedback without a category and a comment',
            ],
            'comment missing' => [
                json_encode([
                    'choices' => [
                        ['message' => ['content' => json_encode(['category' => 'Excellent'])]],
                    ],
                ]),
                'OpenAI returned feedback without a category and a comment',
            ],
        ];
    }

    /**
     * Before this check, every one of these bodies ended in a TypeError the worker could not catch,
     * so the user never got an error message.
     *
     * @throws Exception
     */
    #[Test]
    #[DataProvider('unusableResponses')]
    public function evaluateTranslationThrowsAnExceptionWhenTheResponseHasNoFeedback(string $body, string $expectedMessage): void
    {
        $openAi = $this->createStub(OpenAi::class);
        $openAi->method('chat')->willReturn($body);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage($expectedMessage);

        (new OpenAIClient($openAi))->evaluateTranslation('en-US', 'it-IT', 'Hello', 'Ciao', 'formal');
    }

    /**
     * OpenAI always sends the refusal field; an empty one next to valid content is a normal answer.
     *
     * @throws Exception
     */
    #[Test]
    public function evaluateTranslationAcceptsAnEmptyRefusal(): void
    {
        $openAi = $this->createStub(OpenAi::class);
        $openAi->method('chat')->willReturn(json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode(['category' => 'Good', 'comment' => '**Nice** - work.']),
                        'refusal' => null,
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
        ]));

        $result = (new OpenAIClient($openAi))->evaluateTranslation('en-US', 'it-IT', 'Hello', 'Ciao', 'formal');

        $this->assertSame(['category' => 'Good', 'comment' => 'Nice     work.'], $result);
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
