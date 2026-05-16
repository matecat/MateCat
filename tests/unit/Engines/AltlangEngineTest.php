<?php

declare(strict_types=1);

namespace unit\Engines;

use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Constants\EngineConstants;
use Utils\Engines\Altlang;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;

class TestAltlang extends Altlang
{
    /** @var list<string|bool|null> */
    public array $queuedResponses = [];

    public int $callCount = 0;

    public ?string $lastUrl = null;

    /** @var array<int, mixed> */
    public array $lastCurlOptions = [];

    public function queueResponse(string|bool|null $response): void
    {
        $this->queuedResponses[] = $response;
    }

    public function _call(string $url, array $curl_options = []): string|bool|null
    {
        $this->callCount++;
        $this->lastUrl = $url;
        $this->lastCurlOptions = $curl_options;

        if (empty($this->queuedResponses)) {
            return json_encode(['text' => 'default']);
        }

        return array_shift($this->queuedResponses);
    }

    /**
     * @param mixed $rawValue
     * @param array<string, mixed> $parameters
     */
    public function decodePublic(mixed $rawValue, array $parameters = [], ?string $function = null): GetMemoryResponse
    {
        return $this->_decode($rawValue, $parameters, $function);
    }
}

class AltlangEngineTest extends AbstractTest
{
    private TestAltlang $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $struct = EngineStruct::getStruct();
        $struct->id = 999;
        $struct->name = 'Altlang Test Engine';
        $struct->class_load = 'Altlang';
        $struct->type = EngineConstants::MT;
        $struct->base_url = 'https://altlang.local';
        $struct->translate_relative_url = 'translate';
        $struct->extra_parameters = ['client_secret' => 'secret-for-tests'];

        $this->engine = new TestAltlang($struct);
    }

    #[Test]
    public function getReturnsTranslationAndFillsMissingSegments(): void
    {
        $this->engine->queueResponse(json_encode(['text' => 'Olá mundo']));

        $response = $this->engine->get([
            'segment' => 'Hello world',
            'source' => 'en-GB',
            'target' => 'en-US',
        ]);

        self::assertInstanceOf(GetMemoryResponse::class, $response);
        self::assertSame(1, $this->engine->callCount);
        self::assertNotNull($this->engine->lastUrl);
        self::assertStringContainsString('https://altlang.local/translate', (string)$this->engine->lastUrl);

        self::assertArrayHasKey(CURLOPT_POSTFIELDS, $this->engine->lastCurlOptions);
        $payload = json_decode((string)$this->engine->lastCurlOptions[CURLOPT_POSTFIELDS], true);
        self::assertIsArray($payload);
        self::assertSame('en_GB', $payload['src'] ?? null);
        self::assertSame('en_US', $payload['trg'] ?? null);
        self::assertSame('Hello world', $payload['text'] ?? null);
        self::assertSame('secret-for-tests', $payload['key'] ?? null);

        self::assertNotEmpty($response->matches);
        self::assertSame('Hello world', $response->matches[0]->raw_segment);
        self::assertSame('Hello world', $response->matches[0]->segment);
        self::assertSame('Olá mundo', $response->matches[0]->raw_translation);
    }

    #[Test]
    public function getSkipsAnalysisWithoutCallingTransport(): void
    {
        $this->engine->setAnalysis(true)->setSkipAnalysis(true);

        $response = $this->engine->get([
            'segment' => 'Hello world',
            'source' => 'en-GB',
            'target' => 'en-US',
        ]);

        self::assertSame(0, $this->engine->callCount);
        self::assertInstanceOf(GetMemoryResponse::class, $response);
        self::assertSame(200, $response->responseStatus);
        self::assertEmpty($response->matches);
        self::assertNull($response->error);
    }

    #[Test]
    public function getReturnsErrorResponseWhenEnginePayloadContainsError(): void
    {
        $this->engine->queueResponse(json_encode(['error' => 'service unavailable']));

        $response = $this->engine->get([
            'segment' => 'Hello world',
            'source' => 'fr-FR',
            'target' => 'fr-CA',
        ]);

        self::assertInstanceOf(GetMemoryResponse::class, $response);
        self::assertSame(1, $this->engine->callCount);
        self::assertSame(1, $response->responseStatus);
        self::assertNotNull($response->error);
        self::assertSame(-1, $response->error?->code);
        self::assertSame('service unavailable', $response->error?->message);
        self::assertEmpty($response->matches);
    }

    #[Test]
    public function decodeHandlesStringAndPreDecodedPayloads(): void
    {
        $decodedFromString = $this->engine->decodePublic(
            json_encode(['text' => 'Bonjour']),
            ['data' => json_encode(['text' => 'Hello'])]
        );

        self::assertNotEmpty($decodedFromString->matches);
        self::assertSame('Hello', $decodedFromString->matches[0]->raw_segment);
        self::assertSame('Bonjour', $decodedFromString->matches[0]->raw_translation);

        $decodedFromArray = $this->engine->decodePublic([
            'data' => [
                'translations' => [
                    ['translatedText' => 'Ciao'],
                ],
            ],
        ]);

        self::assertNotEmpty($decodedFromArray->matches);
        self::assertSame('', $decodedFromArray->matches[0]->raw_segment);
        self::assertSame('Ciao', $decodedFromArray->matches[0]->raw_translation);
    }

    #[Test]
    public function setUpdateAndDeleteReturnTrue(): void
    {
        self::assertTrue($this->engine->set([]));
        self::assertTrue($this->engine->update([]));
        self::assertTrue($this->engine->delete([]));
    }
}
