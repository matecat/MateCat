<?php

declare(strict_types=1);

namespace unit\Engines;

use Exception;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Constants\EngineConstants;
use Utils\Engines\Apertium;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;

class ApertiumEngineTest extends AbstractTest
{
    private TestApertium $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $struct = EngineStruct::getStruct();
        $struct->class_load = Apertium::class;
        $struct->name = 'Apertium';
        $struct->type = EngineConstants::MT;
        $struct->base_url = 'https://example.test';
        $struct->translate_relative_url = 'translate';
        $struct->extra_parameters = [
            'client_secret' => 'test-secret',
        ];

        $this->engine = new TestApertium($struct);
    }

    #[Test]
    public function getSuccessReturnsGetMemoryResponseAndBuildsExpectedRequest(): void
    {
        $this->engine->queueRawResponse((string)json_encode([
            'text' => 'Ciao mondo',
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

        $call = $this->engine->getLastCall();
        self::assertNotNull($call);
        self::assertStringContainsString('/translate?', $call['url']);

        parse_str((string)parse_url($call['url'], PHP_URL_QUERY), $queryParams);
        self::assertSame('test-secret', $queryParams['key'] ?? null);
        self::assertSame('translate', $queryParams['func'] ?? null);

        $data = json_decode((string)($queryParams['data'] ?? ''), true);
        self::assertIsArray($data);
        self::assertSame('apertium', $data['mtsystem'] ?? null);
        self::assertSame('en-US', $data['src'] ?? null);
        self::assertSame('it-IT', $data['trg'] ?? null);
        self::assertSame('Hello world', $data['text'] ?? null);
    }

    #[Test]
    public function getWithAnalysisAndSkipAnalysisReturnsEmptyResponseAndSkipsCall(): void
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
        self::assertCount(0, $this->engine->capturedCalls);
    }

    #[Test]
    public function getErrorPathReturnsResponseStatusAtLeast400(): void
    {
        $this->engine->forceErrorOnCall(503, 'Upstream unavailable');

        $response = $this->engine->get([
            'segment' => 'Hello world',
            'source' => 'en-US',
            'target' => 'it-IT',
        ]);

        self::assertInstanceOf(GetMemoryResponse::class, $response);
        self::assertGreaterThanOrEqual(400, $response->responseStatus);
        self::assertSame(503, $response->responseStatus);
        self::assertSame('Upstream unavailable', $response->error?->message);
    }

    #[Test]
    public function decodeApertiumStringFormatBuildsExpectedMatch(): void
    {
        $response = $this->engine->decodeForTest(
            (string)json_encode(['text' => 'Bonjour le monde']),
            [
                'data' => (string)json_encode([
                    'text' => 'Hello world',
                ]),
            ]
        );

        self::assertInstanceOf(GetMemoryResponse::class, $response);
        self::assertSame(200, $response->responseStatus);
        self::assertCount(1, $response->matches);
        self::assertSame('Hello world', $response->matches[0]->raw_segment);
        self::assertSame('Bonjour le monde', $response->matches[0]->raw_translation);
    }

    #[Test]
    public function decodeErrorArrayReturnsErrorResponse(): void
    {
        $response = $this->engine->decodeForTestArray(
            [
                'error' => [
                    'code' => -502,
                    'message' => 'Gateway error',
                ],
            ],
            [
                'data' => (string)json_encode([
                    'text' => 'Hello world',
                ]),
            ]
        );

        self::assertInstanceOf(GetMemoryResponse::class, $response);
        self::assertSame(502, $response->responseStatus);
        self::assertSame(-502, $response->error?->code);
        self::assertSame('Gateway error', $response->error?->message);
    }

    #[Test]
    public function setUpdateDeleteReturnTrue(): void
    {
        self::assertTrue($this->engine->set([]));
        self::assertTrue($this->engine->update([]));
        self::assertTrue($this->engine->delete([]));
    }

    #[Test]
    public function getConfigurationParametersContainsEnableMtAnalysis(): void
    {
        self::assertSame(['enable_mt_analysis'], Apertium::getConfigurationParameters());
    }

    #[Test]
    public function constructorThrowsWhenEngineTypeIsNotMT(): void
    {
        $struct = EngineStruct::getStruct();
        $struct->class_load = Apertium::class;
        $struct->name = 'Apertium';
        $struct->type = EngineConstants::TM;
        $struct->base_url = 'https://example.test';
        $struct->translate_relative_url = 'translate';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('is not a MT engine');

        new TestApertium($struct);
    }
}

class TestApertium extends Apertium
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
