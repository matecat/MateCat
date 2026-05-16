<?php

declare(strict_types=1);

namespace unit\Engines;

use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Constants\EngineConstants;
use Utils\Engines\GoogleTranslate;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Exception;

class GoogleTranslateEngineTest extends AbstractTest
{
    private TestGoogleTranslate $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $struct = EngineStruct::getStruct();
        $struct->class_load = GoogleTranslate::class;
        $struct->name = 'Google';
        $struct->type = EngineConstants::MT;
        $struct->base_url = 'https://example.test';
        $struct->translate_relative_url = 'translate';
        $struct->extra_parameters = [
            'client_secret' => 'test-secret',
        ];

        $this->engine = new TestGoogleTranslate($struct);
    }

    #[Test]
    public function getSuccessReturnsGetMemoryResponseWithMatch(): void
    {
        $this->engine->queueRawResponse((string)json_encode([
            'data' => [
                'translations' => [
                    ['translatedText' => 'Ciao mondo'],
                ],
            ],
        ]));

        $response = $this->engine->get([
            'segment' => 'Hello world',
            'source' => 'en-US',
            'target' => 'it-IT',
        ]);

        self::assertInstanceOf(GetMemoryResponse::class, $response);
        self::assertSame(200, $response->responseStatus);
        self::assertCount(1, $response->matches);
        self::assertSame('Hello world', $response->matches[0]->raw_segment);
        self::assertSame('Ciao mondo', $response->matches[0]->raw_translation);
    }

    #[Test]
    public function getWithAnalysisAndSkipAnalysisReturnsEmptyResponse(): void
    {
        $response = $this->engine
            ->setAnalysis(true)
            ->setSkipAnalysis(true)
            ->get([
                'segment' => 'Hello world',
                'source' => 'en-US',
                'target' => 'it-IT',
            ]);

        self::assertInstanceOf(GetMemoryResponse::class, $response);
        self::assertSame(200, $response->responseStatus);
        self::assertSame([], $response->matches);
    }

    #[Test]
    public function getErrorPathReturnsResponseStatusAtLeast400(): void
    {
        $this->engine->forceErrorOnCall(502, 'Bad gateway');

        $response = $this->engine->get([
            'segment' => 'Hello world',
            'source' => 'en-US',
            'target' => 'it-IT',
        ]);

        self::assertInstanceOf(GetMemoryResponse::class, $response);
        self::assertGreaterThanOrEqual(400, $response->responseStatus);
        self::assertSame(502, $response->responseStatus);
    }

    #[Test]
    public function decodeWithGoogleV2ResponseBuildsExpectedMatch(): void
    {
        $response = $this->engine->decodeForTest((string)json_encode([
            'data' => [
                'translations' => [
                    ['translatedText' => 'Bonjour le monde'],
                ],
            ],
        ]), [
            'q' => 'Hello world',
        ]);

        self::assertInstanceOf(GetMemoryResponse::class, $response);
        self::assertCount(1, $response->matches);
        self::assertSame('Hello world', $response->matches[0]->raw_segment);
        self::assertSame('Bonjour le monde', $response->matches[0]->raw_translation);
    }

    #[Test]
    public function setUpdateDeleteReturnTrue(): void
    {
        self::assertTrue($this->engine->set([]));
        self::assertTrue($this->engine->update([]));
        self::assertTrue($this->engine->delete([]));
    }

    #[Test]
    public function fixLangCodeNormalizesRegionalTagsToPrimaryCode(): void
    {
        self::assertSame('en', $this->engine->fixLangCodeForTest('EN-US'));
        self::assertSame('zh', $this->engine->fixLangCodeForTest(' zh-CN '));
    }

    #[Test]
    public function getUsesNormalizedLangCodesInCallParameters(): void
    {
        $this->engine->queueRawResponse((string)json_encode([
            'data' => [
                'translations' => [
                    ['translatedText' => 'Ciao mondo'],
                ],
            ],
        ]));

        $this->engine->get([
            'segment' => 'Hello world',
            'source' => 'EN-US',
            'target' => 'IT-IT',
        ]);

        $call = $this->engine->getLastCall();

        self::assertNotNull($call);
        self::assertArrayHasKey(CURLOPT_POSTFIELDS, $call['curl_options']);
        self::assertSame('en', $call['curl_options'][CURLOPT_POSTFIELDS]['source']);
        self::assertSame('it', $call['curl_options'][CURLOPT_POSTFIELDS]['target']);
    }

    #[Test]
    public function decodeWithErrorPayloadStringThrowsExceptionWithApiMessage(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Blocked key');

        $this->engine->decodeForTest((string)json_encode([
            'error' => [
                'response' => (string)json_encode([
                    'error' => [
                        'message' => 'Blocked key',
                        'code' => 403,
                    ],
                ]),
            ],
        ]), [
            'q' => 'Hello world',
        ]);
    }

    #[Test]
    public function decodeWithAlreadyDecodedErrorArrayThrowsExceptionWithMessage(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Upstream error');

        $this->engine->decodeForTestArray([
            'error' => [
                'message' => 'Upstream error',
            ],
        ], [
            'q' => 'Hello world',
        ]);
    }

    #[Test]
    public function constructorThrowsWhenEngineTypeIsNotMT(): void
    {
        $struct = EngineStruct::getStruct();
        $struct->class_load = GoogleTranslate::class;
        $struct->name = 'Google';
        $struct->type = EngineConstants::TM;
        $struct->base_url = 'https://example.test';
        $struct->translate_relative_url = 'translate';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('is not a MT engine');

        new TestGoogleTranslate($struct);
    }

    #[Test]
    public function getConfigurationParametersContainsEnableMtAnalysis(): void
    {
        self::assertSame(['enable_mt_analysis'], GoogleTranslate::getConfigurationParameters());
    }
}

class TestGoogleTranslate extends GoogleTranslate
{
    /** @var list<array{url:string,curl_options:array<int,mixed>}> */
    public array $capturedCalls = [];

    /** @var list<string|bool|null> */
    private array $queuedRawResponses = [];

    private ?int $forcedErrorStatus = null;
    private string $forcedErrorMessage = 'forced error';

    public function queueRawResponse(string|bool|null $raw): void
    {
        $this->queuedRawResponses[] = $raw;
    }

    public function forceErrorOnCall(int $status, string $message): void
    {
        $this->forcedErrorStatus = $status;
        $this->forcedErrorMessage = $message;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function decodeForTest(string $rawValue, array $parameters): GetMemoryResponse
    {
        return $this->_decode($rawValue, $parameters);
    }

    /**
     * @param array<string, mixed> $rawValue
     * @param array<string, mixed> $parameters
     */
    public function decodeForTestArray(array $rawValue, array $parameters): GetMemoryResponse
    {
        return $this->_decode($rawValue, $parameters);
    }

    public function fixLangCodeForTest(string $lang): string
    {
        return $this->_fixLangCode($lang);
    }

    /**
     * @return array{url:string,curl_options:array<int,mixed>}|null
     */
    public function getLastCall(): ?array
    {
        if (empty($this->capturedCalls)) {
            return null;
        }

        return $this->capturedCalls[array_key_last($this->capturedCalls)];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function call(string $function, array $parameters = [], bool $isPostRequest = false, bool $isJsonRequest = false): void
    {
        if ($this->forcedErrorStatus !== null) {
            $this->result = [
                'error' => [
                    'code' => -$this->forcedErrorStatus,
                    'message' => $this->forcedErrorMessage,
                ],
            ];

            return;
        }

        parent::call($function, $parameters, $isPostRequest, $isJsonRequest);
    }

    /**
     * @param array<int, mixed> $curl_options
     */
    public function _call(string $url, array $curl_options = []): string|bool|null
    {
        $this->capturedCalls[] = [
            'url' => $url,
            'curl_options' => $curl_options,
        ];

        return array_shift($this->queuedRawResponses);
    }
}
