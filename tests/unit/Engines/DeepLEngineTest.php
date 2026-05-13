<?php

declare(strict_types=1);

namespace unit\Engines;

use DateTime;
use DeepL\DeepLException;
use DeepL\GlossaryEntries;
use DeepL\GlossaryInfo;
use DeepL\TextResult;
use DeepL\Translator;
use DomainException;
use Exception;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Constants\EngineConstants;
use Utils\Engines\DeepL;
use Utils\Engines\DeepL\DeepLApiClient;
use Utils\Engines\DeepL\DeepLApiException;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;

class DeepLEngineTest extends AbstractTest
{
    private TestDeepL $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $struct = EngineStruct::getStruct();
        $struct->class_load = DeepL::class;
        $struct->name = 'DeepL';
        $struct->type = EngineConstants::MT;
        $struct->base_url = 'https://example.test';
        $struct->translate_relative_url = 'translate';
        $struct->extra_parameters = [
            'DeepL-Auth-Key' => 'test-auth-key',
        ];

        $this->engine = new TestDeepL($struct);
    }

    #[Test]
    public function getSuccessReturnsGetMemoryResponseWithMatch(): void
    {
        $this->engine->setNextRawResponse((string)json_encode([
            'translations' => [
                [
                    'detected_source_language' => 'EN',
                    'text' => 'Ciao &quot;Mondo&quot;',
                ],
            ],
        ]));

        $response = $this->engine->get([
            'segment' => 'Hello world',
            'source' => 'en-US',
            'target' => 'it-IT',
            'pid' => 1,
        ]);

        self::assertInstanceOf(GetMemoryResponse::class, $response);
        self::assertSame(200, $response->responseStatus);
        self::assertCount(1, $response->matches);
        self::assertSame('Hello world', $response->matches[0]->raw_segment);
        self::assertSame('Ciao "Mondo"', $response->matches[0]->raw_translation);
        self::assertSame('en', $response->matches[0]->source);
        self::assertSame('it', $response->matches[0]->target);
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
                'pid' => 1,
            ]);

        self::assertInstanceOf(GetMemoryResponse::class, $response);
        self::assertSame(200, $response->responseStatus);
        self::assertSame([], $response->matches);
    }

    #[Test]
    public function getErrorPathReturnsResponseStatusAtLeast400(): void
    {
        $this->engine->forceErrorOnCall(503, 'Service unavailable');

        $response = $this->engine->get([
            'segment' => 'Hello world',
            'source' => 'en-US',
            'target' => 'it-IT',
            'pid' => 1,
        ]);

        self::assertInstanceOf(GetMemoryResponse::class, $response);
        self::assertGreaterThanOrEqual(400, $response->responseStatus);
        self::assertSame(503, $response->responseStatus);
    }

    #[Test]
    public function decodeWithValidDeepLJsonFixtureBuildsExpectedMatch(): void
    {
        $response = $this->engine->decodeForTest((string)json_encode([
            'translations' => [
                [
                    'text' => 'Salut',
                ],
            ],
        ]), [
            'source_lang' => 'EN',
            'target_lang' => 'FR',
            'text' => ['Hello'],
        ]);

        self::assertInstanceOf(GetMemoryResponse::class, $response);
        self::assertCount(1, $response->matches);
        self::assertSame('Hello', $response->matches[0]->raw_segment);
        self::assertSame('Salut', $response->matches[0]->raw_translation);
    }

    #[Test]
    public function decodeWith403PayloadThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('API blocked');

        $this->engine->decodeForTest((string)json_encode([
            'error' => [
                'response' => (string)json_encode([
                    'message' => 'API blocked',
                ]),
            ],
            'responseStatus' => 403,
        ]), [
            'source_lang' => 'EN',
            'target_lang' => 'FR',
            'text' => ['Hello'],
        ]);
    }

    #[Test]
    public function setThrowsDomainException(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Method set not implemented.');

        $this->engine->set([]);
    }

    #[Test]
    public function updateThrowsDomainException(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Method update not implemented.');

        $this->engine->update([]);
    }

    #[Test]
    public function deleteThrowsDomainException(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Method delete not implemented.');

        $this->engine->delete([]);
    }

    #[Test]
    public function glossariesCrudMethodsDelegateToApiClient(): void
    {
        $client = $this->createMock(DeepLApiClient::class);

        $client->expects(self::once())
            ->method('allGlossaries')
            ->willReturn(['glossaries' => [['glossary_id' => 'g1']]]);

        $client->expects(self::once())
            ->method('getGlossary')
            ->with('g1')
            ->willReturn(['glossary_id' => 'g1', 'name' => 'Glossary 1']);

        $client->expects(self::once())
            ->method('deleteGlossary')
            ->with('g2')
            ->willReturn(['id' => 'g2']);

        $payload = [
            'name' => 'My Glossary',
            'source_lang' => 'en',
            'target_lang' => 'it',
            'entries' => [['hello', 'ciao']],
            'entries_format' => 'csv',
        ];

        $client->expects(self::once())
            ->method('createGlossary')
            ->with($payload)
            ->willReturn(['glossary_id' => 'g3']);

        $client->expects(self::once())
            ->method('getGlossaryEntries')
            ->with('g3')
            ->willReturn(['hello' => 'ciao']);

        $this->engine->setMockClient($client);

        self::assertSame(['glossaries' => [['glossary_id' => 'g1']]], $this->engine->glossaries());
        self::assertSame(['glossary_id' => 'g1', 'name' => 'Glossary 1'], $this->engine->getGlossary('g1'));
        self::assertSame(['id' => 'g2'], $this->engine->deleteGlossary('g2'));
        self::assertSame(['glossary_id' => 'g3'], $this->engine->createGlossary($payload));
        self::assertSame(['hello' => 'ciao'], $this->engine->getGlossaryEntries('g3'));
    }

    #[Test]
    public function parentGetClientThrowsWhenApiKeyMissing(): void
    {
        $struct = EngineStruct::getStruct();
        $struct->class_load = DeepL::class;
        $struct->name = 'DeepL';
        $struct->type = EngineConstants::MT;
        $struct->extra_parameters = [];

        $engine = new TestDeepL($struct);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('API ket not set');

        $engine->callParentGetClient();
    }

    #[Test]
    public function getConfigurationParametersExposeDeepLMetadataKeys(): void
    {
        self::assertSame([
            'enable_mt_analysis',
            'deepl_formality',
            'deepl_id_glossary',
            'deepl_engine_type',
        ], DeepL::getConfigurationParameters());
    }

    #[Test]
    public function deepLApiClientTranslateBuildsExpectedResponseAndOptions(): void
    {
        $textResult = $this->createStub(TextResult::class);
        $textResult->text = 'Ciao mondo';
        $textResult->detectedSourceLang = 'EN';

        $translator = $this->createMock(Translator::class);
        $translator->expects(self::once())
            ->method('translateText')
            ->with('Hello world', 'en', 'it', ['formality' => 'more', 'glossary' => 'gid'])
            ->willReturn($textResult);

        $client = DeepLApiClient::newInstanceWithTranslator($translator);
        $result = $client->translate('Hello world', 'en', 'it', 'more', 'gid');

        self::assertSame('EN', $result['translations'][0]['detected_source_language']);
        self::assertSame('Ciao mondo', $result['translations'][0]['text']);
    }

    #[Test]
    public function deepLApiClientTranslateWrapsDeeplExceptions(): void
    {
        $translator = $this->createMock(Translator::class);
        $translator->expects(self::once())
            ->method('translateText')
            ->willThrowException(new DeepLException('Quota exceeded', 429));

        $client = DeepLApiClient::newInstanceWithTranslator($translator);

        $this->expectException(DeepLApiException::class);
        $this->expectExceptionMessage('Quota exceeded');

        $client->translate('Hello world', 'en', 'it');
    }

    #[Test]
    public function deepLApiClientAllGlossariesReturnsMappedPayload(): void
    {
        $g1 = new GlossaryInfo('g1', 'Glossary One', true, 'en', 'it', new DateTime('2026-01-15T10:00:00Z'), 2);
        $g2 = new GlossaryInfo('g2', 'Glossary Two', false, 'en', 'de', new DateTime('2026-02-20T12:30:00Z'), 4);

        $translator = $this->createMock(Translator::class);
        $translator->expects(self::once())->method('listGlossaries')->willReturn([$g1, $g2]);

        $client = DeepLApiClient::newInstanceWithTranslator($translator);
        $result = $client->allGlossaries();

        self::assertCount(2, $result['glossaries']);
        self::assertSame('g1', $result['glossaries'][0]['glossary_id']);
        self::assertSame('Glossary Two', $result['glossaries'][1]['name']);
    }

    #[Test]
    public function deepLApiClientAllGlossariesWrapsDeeplExceptions(): void
    {
        $translator = $this->createMock(Translator::class);
        $translator->expects(self::once())->method('listGlossaries')->willThrowException(new DeepLException('Unauthorized', 401));

        $client = DeepLApiClient::newInstanceWithTranslator($translator);

        $this->expectException(DeepLApiException::class);
        $this->expectExceptionMessage('Unauthorized');

        $client->allGlossaries();
    }

    #[Test]
    public function deepLApiClientCreateGlossarySupportsCsvTsvAndFallbackFormats(): void
    {
        $csvInfo = new GlossaryInfo('csv-id', 'CSV', true, 'en', 'de', new DateTime('2026-03-01T08:00:00Z'), 3);
        $tsvInfo = new GlossaryInfo('tsv-id', 'TSV', true, 'en', 'fr', new DateTime('2026-03-01T09:00:00Z'), 2);
        $fallbackInfo = new GlossaryInfo('fb-id', 'FB', false, 'en', 'es', new DateTime('2026-03-01T10:00:00Z'), 1);

        $csvTranslator = $this->createMock(Translator::class);
        $csvTranslator->expects(self::once())
            ->method('createGlossaryFromCsv')
            ->with('CSV', 'en', 'de', "a,b\nc,d\ne,f")
            ->willReturn($csvInfo);
        $csvClient = DeepLApiClient::newInstanceWithTranslator($csvTranslator);

        $tsvTranslator = $this->createMock(Translator::class);
        $tsvTranslator->expects(self::once())
            ->method('createGlossary')
            ->with(
                'TSV',
                'en',
                'fr',
                self::isInstanceOf(GlossaryEntries::class)
            )
            ->willReturn($tsvInfo);
        $tsvClient = DeepLApiClient::newInstanceWithTranslator($tsvTranslator);

        $fallbackTranslator = $this->createMock(Translator::class);
        $fallbackTranslator->expects(self::once())
            ->method('createGlossaryFromCsv')
            ->with('FB', 'en', 'es', "x,y")
            ->willReturn($fallbackInfo);
        $fallbackClient = DeepLApiClient::newInstanceWithTranslator($fallbackTranslator);

        $csv = $csvClient->createGlossary([
            'name' => 'CSV',
            'source_lang' => 'en',
            'target_lang' => 'de',
            'entries' => [['a', 'b'], ['c', 'd'], ['e', 'f']],
            'entries_format' => 'csv',
        ]);

        $tsv = $tsvClient->createGlossary([
            'name' => 'TSV',
            'source_lang' => 'en',
            'target_lang' => 'fr',
            'entries' => "hello\tbonjour\nworld\tmonde",
            'entries_format' => 'tsv',
        ]);

        $fallback = $fallbackClient->createGlossary([
            'name' => 'FB',
            'source_lang' => 'en',
            'target_lang' => 'es',
            'entries' => [['x', 'y']],
            'entries_format' => 'json',
        ]);

        self::assertSame('csv-id', $csv['glossary_id']);
        self::assertSame('tsv-id', $tsv['glossary_id']);
        self::assertSame('fb-id', $fallback['glossary_id']);
    }

    #[Test]
    public function deepLApiClientCreateGlossaryWrapsDeeplExceptions(): void
    {
        $translator = $this->createMock(Translator::class);
        $translator->expects(self::once())
            ->method('createGlossaryFromCsv')
            ->willThrowException(new DeepLException('Bad request', 400));

        $client = DeepLApiClient::newInstanceWithTranslator($translator);

        $this->expectException(DeepLApiException::class);
        $this->expectExceptionMessage('Bad request');

        $client->createGlossary([
            'name' => 'CSV',
            'source_lang' => 'en',
            'target_lang' => 'de',
            'entries' => [['a', 'b']],
            'entries_format' => 'csv',
        ]);
    }

    #[Test]
    public function deepLApiClientDeleteGetAndEntriesWorkAndWrapExceptions(): void
    {
        $glossary = new GlossaryInfo('g-1', 'Name', true, 'en', 'it', new DateTime('2026-03-10T08:00:00Z'), 7);
        $entries = GlossaryEntries::fromTsv("Hello\tCiao\nWorld\tMondo");

        $translator = $this->createMock(Translator::class);
        $translator->expects(self::once())->method('deleteGlossary')->with('g-1');
        $translator->expects(self::once())->method('getGlossary')->with('g-1')->willReturn($glossary);
        $translator->expects(self::once())->method('getGlossaryEntries')->with('g-1')->willReturn($entries);

        $client = DeepLApiClient::newInstanceWithTranslator($translator);

        self::assertSame(['id' => 'g-1'], $client->deleteGlossary('g-1'));
        self::assertSame('g-1', $client->getGlossary('g-1')['glossary_id']);
        self::assertSame(['Hello' => 'Ciao', 'World' => 'Mondo'], $client->getGlossaryEntries('g-1'));
    }

    #[Test]
    public function deepLApiClientDeleteGetAndEntriesWrapExceptions(): void
    {
        $deleteTranslator = $this->createMock(Translator::class);
        $deleteTranslator->expects(self::once())->method('deleteGlossary')->willThrowException(new DeepLException('Not found', 404));
        $deleteClient = DeepLApiClient::newInstanceWithTranslator($deleteTranslator);

        try {
            $deleteClient->deleteGlossary('missing');
            self::fail('Expected DeepLApiException for deleteGlossary');
        } catch (DeepLApiException $e) {
            self::assertSame('Not found', $e->getMessage());
        }

        $getTranslator = $this->createMock(Translator::class);
        $getTranslator->expects(self::once())->method('getGlossary')->willThrowException(new DeepLException('Glossary not found', 404));
        $getClient = DeepLApiClient::newInstanceWithTranslator($getTranslator);

        try {
            $getClient->getGlossary('missing');
            self::fail('Expected DeepLApiException for getGlossary');
        } catch (DeepLApiException $e) {
            self::assertSame('Glossary not found', $e->getMessage());
        }

        $entriesTranslator = $this->createMock(Translator::class);
        $entriesTranslator->expects(self::once())->method('getGlossaryEntries')->willThrowException(new DeepLException('Forbidden', 403));
        $entriesClient = DeepLApiClient::newInstanceWithTranslator($entriesTranslator);

        try {
            $entriesClient->getGlossaryEntries('forbidden');
            self::fail('Expected DeepLApiException for getGlossaryEntries');
        } catch (DeepLApiException $e) {
            self::assertSame('Forbidden', $e->getMessage());
        }
    }
}

class TestDeepL extends DeepL
{
    private ?DeepLApiClient $mockClient = null;
    private ?string $nextRawResponse = null;
    private ?int $forcedErrorStatus = null;
    private string $forcedErrorMessage = 'forced error';

    public function setMockClient(DeepLApiClient $client): void
    {
        $this->mockClient = $client;
    }

    public function setNextRawResponse(string $raw): void
    {
        $this->nextRawResponse = $raw;
    }

    public function forceErrorOnCall(int $status, string $message): void
    {
        $this->forcedErrorStatus = $status;
        $this->forcedErrorMessage = $message;
    }

    protected function _getClient(): DeepLApiClient
    {
        if ($this->mockClient !== null) {
            return $this->mockClient;
        }

        return parent::_getClient();
    }

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

    public function _call(string $url, array $curl_options = []): string|bool|null
    {
        if ($this->nextRawResponse !== null) {
            $raw = $this->nextRawResponse;
            $this->nextRawResponse = null;

            return $raw;
        }

        return parent::_call($url, $curl_options);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function decodeForTest(string $rawValue, array $parameters): GetMemoryResponse
    {
        return $this->_decode($rawValue, $parameters);
    }

    /**
     * @throws Exception
     */
    public function callParentGetClient(): DeepLApiClient
    {
        return parent::_getClient();
    }
}
