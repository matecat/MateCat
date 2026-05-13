<?php

declare(strict_types=1);

namespace unit\Engines;

use Exception;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Constants\EngineConstants;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\Engines\YandexTranslate;

class YandexTranslateEngineTest extends AbstractTest
{
    private TestYandexTranslate $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $struct = EngineStruct::getStruct();
        $struct->class_load = YandexTranslate::class;
        $struct->name = 'Yandex';
        $struct->type = EngineConstants::MT;
        $struct->base_url = 'https://example.test';
        $struct->translate_relative_url = 'translate';
        $struct->extra_parameters = [
            'client_secret' => 'test-secret',
        ];

        $this->engine = new TestYandexTranslate($struct);
    }

    #[Test]
    public function getSuccessReturnsTranslatedMatch(): void
    {
        $this->engine->queueRawResponse((string)json_encode([
            'code' => 200,
            'text' => ['Ciao mondo'],
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

        $lastCall = $this->engine->getLastCall();
        self::assertNotNull($lastCall);
        self::assertStringContainsString('/translate', $lastCall['url']);
        self::assertArrayHasKey(CURLOPT_POSTFIELDS, $lastCall['curl_options']);
        self::assertIsArray($lastCall['curl_options'][CURLOPT_POSTFIELDS]);
        self::assertSame('matecat', $lastCall['curl_options'][CURLOPT_POSTFIELDS]['srv']);
        self::assertSame('en-it', $lastCall['curl_options'][CURLOPT_POSTFIELDS]['lang']);
        self::assertSame('Hello world', $lastCall['curl_options'][CURLOPT_POSTFIELDS]['text']);
    }

    #[Test]
    public function getWithSkipAnalysisReturnsEmptyResponse(): void
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
    public function getErrorReturnsResponseStatusAtLeast400(): void
    {
        $this->engine->queueRawResponse((string)json_encode([
            'code' => -503,
            'message' => 'Upstream unavailable',
        ]));

        $response = $this->engine->get([
            'segment' => 'Hello world',
            'source' => 'en-US',
            'target' => 'it-IT',
        ]);

        self::assertGreaterThanOrEqual(400, $response->responseStatus);
        self::assertSame(503, $response->responseStatus);
        self::assertSame(-503, $response->error->code);
        self::assertSame('Upstream unavailable', $response->error->message);
    }

    #[Test]
    public function decodeYandexSuccessPayloadReturnsExpectedMatch(): void
    {
        $response = $this->engine->decodeForTest((string)json_encode([
            'code' => 200,
            'text' => ['Bonjour le monde'],
        ]), [
            'text' => 'Hello world',
        ]);

        self::assertCount(1, $response->matches);
        self::assertSame('Hello world', $response->matches[0]->raw_segment);
        self::assertSame('Bonjour le monde', $response->matches[0]->raw_translation);
    }

    #[Test]
    public function decodeErrorArrayUsesNestedResponseCodeAndMessage(): void
    {
        $response = $this->engine->decodeForTestArray([
            'error' => [
                'code' => -1,
                'message' => 'Generic error',
                'response' => (string)json_encode([
                    'code' => -429,
                    'message' => 'Rate limit',
                ]),
            ],
        ], [
            'text' => 'Hello world',
        ]);

        self::assertSame(429, $response->responseStatus);
        self::assertSame(-429, $response->error->code);
        self::assertSame('Rate limit', $response->error->message);
    }

    #[Test]
    public function setUpdateDeleteReturnTrue(): void
    {
        self::assertTrue($this->engine->set([]));
        self::assertTrue($this->engine->update([]));
        self::assertTrue($this->engine->delete([]));
    }

    #[Test]
    public function constructorThrowsWhenEngineTypeIsNotMT(): void
    {
        $struct = EngineStruct::getStruct();
        $struct->class_load = YandexTranslate::class;
        $struct->name = 'Yandex';
        $struct->type = EngineConstants::TM;
        $struct->base_url = 'https://example.test';
        $struct->translate_relative_url = 'translate';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('is not a MT engine');

        new TestYandexTranslate($struct);
    }

    #[Test]
    public function getConfigurationParametersContainsEnableMtAnalysis(): void
    {
        self::assertSame(['enable_mt_analysis'], YandexTranslate::getConfigurationParameters());
    }
}

class TestYandexTranslate extends YandexTranslate
{
    /** @var list<array{url:string,curl_options:array<int,mixed>}> */
    private array $capturedCalls = [];

    /** @var list<string|bool|null> */
    private array $queuedRawResponses = [];

    public function queueRawResponse(string|bool|null $raw): void
    {
        $this->queuedRawResponses[] = $raw;
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
