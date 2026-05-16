<?php

/** @noinspection PhpConditionAlreadyCheckedInspection */
declare(strict_types=1);
namespace unit\Engines;
use Exception;
use InvalidArgumentException;
use Lara\Glossaries;
use Lara\LaraException;
use Lara\Memories;
use Lara\Memory;
use Lara\TextBlock;
use Lara\TextResult;
use Lara\AccessKey;
use Lara\LaraApiException;
use Lara\Internal\HttpClient;
use Model\Users\UserStruct;
use Model\Engines\Structs\EngineStruct;
use Model\TmKeyManagement\MemoryKeyStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use RuntimeException;
use stdClass;
use TestHelpers\AbstractTest;
use Throwable;
use Utils\Constants\EngineConstants;
use Utils\Engines\Lara;
use Utils\Engines\Lara\HeaderField;
use Utils\Engines\Lara\Headers;
use Utils\Engines\Lara\HttpClientInterface;
use Utils\Engines\Lara\LaraClient;
use Utils\Engines\MMT;
use Utils\Engines\MMT\MMTServiceApiException;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\Redis\RedisHandler;
use Utils\TmKeyManagement\TmKeyStruct;
class LaraEngineTest extends AbstractTest
{
    private TestLara $engine;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();
        $struct = EngineStruct::getStruct();
        $struct->class_load = Lara::class;
        $struct->name = 'Lara';
        $struct->type = EngineConstants::MT;
        $struct->extra_parameters = [
            'Lara-AccessKeyId' => 'id',
            'Lara-AccessKeySecret' => 'secret',
            'MMT-License' => 'license',
        ];
        $this->engine = new TestLara($struct);
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function getSuccessReturnsGetMemoryResponseWithMatch(): void
    {
        $httpClient = new TestHttpClient();
        $client = $this->createMock(LaraClient::class);
        $client->method('getHttpClient')->willReturn($httpClient);
        $client->expects(self::once())
            ->method('translate')
            ->willReturn(new TextResult('application/xliff+xml', 'en-US', [
                new TextBlock('translated segment', true),
            ]));
        $this->engine->setMockClient($client);
        $response = $this->engine->get([
            'segment' => 'source segment',
            'source' => 'en-US',
            'target' => 'it-IT',
            'keys' => ['k1', 'k2'],
            'context_list_before' => [],
            'context_list_after' => [],
        ]);
        self::assertInstanceOf(GetMemoryResponse::class, $response);
        self::assertSame(200, $response->responseStatus);
        self::assertCount(1, $response->matches);
        self::assertSame('translated segment', $response->matches[0]->raw_translation);
        self::assertSame('ext_my_k1,ext_my_k2', $httpClient->capturedHeaders[Headers::LARA_MEMORIES_IDS] ?? null);
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function getSuccessWithTuidStyleContextAndScoreCoversAdvancedPath(): void
    {
        $httpClient = new TestHttpClient();
        $client = $this->createMock(LaraClient::class);
        $client->method('getHttpClient')->willReturn($httpClient);
        $client->expects(self::once())
            ->method('translate')
            ->willReturn(new TextResult('application/xliff+xml', 'en-US', [
                new TextBlock('<b>locked</b>', false),
                new TextBlock('advanced translation', true),
            ]));
        $this->engine->setMockClient($client);
        $fallback = $this->createMock(MMT::class);
        $fallback->expects(self::once())
            ->method('getQualityEstimation')
            ->with('en-US', 'it-IT', 'source segment', 'advanced translation', '2')
            ->willReturn(0.88);
        $this->engine->setMmtFallback($fallback);
        $response = $this->engine->get([
            'segment' => 'source segment',
            'source' => 'en-US',
            'target' => 'it-IT',
            'keys' => ['k1'],
            'context_list_before' => ['before context'],
            'context_list_after' => ['after context'],
            'include_score' => true,
            'mt_qe_engine_id' => '2',
            'tuid' => 'tuid-advanced',
            'lara_style' => 'fluid',
        ]);
        self::assertSame(200, $response->responseStatus);
        self::assertSame('advanced translation', $response->matches[0]->raw_translation);
        self::assertSame('ext_my_k1', $httpClient->capturedHeaders[Headers::LARA_MEMORIES_IDS] ?? null);
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function getInAnalysisModeUsesIdUserKeysForContribution(): void
    {
        $httpClient = new TestHttpClient();
        $client = $this->createMock(LaraClient::class);
        $client->method('getHttpClient')->willReturn($httpClient);
        $client->expects(self::once())
            ->method('translate')
            ->willReturn(new TextResult('application/xliff+xml', 'en-US', [
                new TextBlock('analysis translation', true),
            ]));
        $this->engine->setMockClient($client);
        $this->engine->setAnalysis(true)->setSkipAnalysis(false);
        $response = $this->engine->get([
            'segment' => 'source segment',
            'source' => 'en-US',
            'target' => 'it-IT',
            'id_user' => ['u1', 'u2'],
            'context_list_before' => [],
            'context_list_after' => [],
        ]);
        self::assertSame(200, $response->responseStatus);
        self::assertSame('analysis translation', $response->matches[0]->raw_translation);
        self::assertSame('ext_my_u1,ext_my_u2', $httpClient->capturedHeaders[Headers::LARA_MEMORIES_IDS] ?? null);
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function getWithAnalysisAndSkipAnalysisReturnsEmptyResponse(): void
    {
        $response = $this->engine
            ->setAnalysis()
            ->setSkipAnalysis()
            ->get([
                'segment' => 'source segment',
                'source' => 'en-US',
                'target' => 'it-IT',
            ]);
        self::assertInstanceOf(GetMemoryResponse::class, $response);
        self::assertSame(200, $response->responseStatus);
        self::assertSame([], $response->matches);
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function getWithUrLatnPkTargetReturnsEmptyResponse(): void
    {
        $response = $this->engine->get([
            'segment' => 'source segment',
            'source' => 'en-US',
            'target' => 'ur-Latn-PK',
        ]);
        self::assertInstanceOf(GetMemoryResponse::class, $response);
        self::assertSame(200, $response->responseStatus);
        self::assertSame([], $response->matches);
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function getErrorFromLaraReturnsFallbackErrorResponse(): void
    {
        $httpClient = new TestHttpClient();
        $client = $this->createStub(LaraClient::class);
        $client->method('getHttpClient')->willReturn($httpClient);
        $client->method('translate')->willThrowException(new LaraException('failure', 500));
        $this->engine->setMockClient($client);
        $fallback = $this->createMock(MMT::class);
        $fallbackResponse = new GetMemoryResponse([
            'responseStatus' => 500,
            'responseDetails' => 'fallback failed',
            'matches' => [],
        ]);
        $fallback->expects(self::once())
            ->method('get')
            ->willReturn($fallbackResponse);
        $this->engine->setMmtFallback($fallback);
        $response = $this->engine->get([
            'segment' => 'source segment',
            'source' => 'en-US',
            'target' => 'it-IT',
            'job_id' => 1,
            'keys' => ['k1'],
            'context_list_before' => [],
            'context_list_after' => [],
        ]);
        self::assertSame(500, $response->responseStatus);
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function get429WithMalformedUtf8MessageReturnsEmptyResponseWithoutFallback(): void
    {
        $httpClient = new TestHttpClient();
        $client = $this->createStub(LaraClient::class);
        $client->method('getHttpClient')->willReturn($httpClient);
        $client->method('translate')->willThrowException(new LaraException("\xB1\x31", 429));
        $this->engine->setMockClient($client);
        $fallback = $this->createMock(MMT::class);
        $fallback->expects(self::never())->method('get');
        $this->engine->setMmtFallback($fallback);
        $response = $this->engine->get([
            'segment' => 'source segment',
            'source' => 'en-US',
            'target' => 'it-IT',
            'job_id' => 1,
            'keys' => ['k1'],
            'context_list_before' => [],
            'context_list_after' => [],
        ]);
        self::assertInstanceOf(GetMemoryResponse::class, $response);
        self::assertSame(200, $response->responseStatus);
        self::assertSame([], $response->matches);
    }
    #[Test]
    public function getQualityEstimationSuccessReturnsFloat(): void
    {
        $fallback = $this->createMock(MMT::class);
        $fallback->expects(self::once())
            ->method('getQualityEstimation')
            ->with('en-US', 'it-IT', 'source', 'translation', '2')
            ->willReturn(0.91);
        $this->engine->setMmtFallback($fallback);
        $result = $this->engine->getQualityEstimation('en-US', 'it-IT', 'source', 'translation');
        self::assertSame(0.91, $result);
    }
    #[Test]
    public function getQualityEstimationErrorReturnsNull(): void
    {
        $fallback = $this->createMock(MMT::class);
        $fallback->expects(self::once())
            ->method('getQualityEstimation')
            ->willThrowException(new MMTServiceApiException('Error', 500, 'failed'));
        $this->engine->setMmtFallback($fallback);
        $result = $this->engine->getQualityEstimation('en-US', 'it-IT', 'source', 'translation');
        self::assertNull($result);
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function getWithProvidedTranslationBuildsThinkMatchWithoutCallingClient(): void
    {
        $response = $this->engine->get([
            'segment' => 'source segment',
            'translation' => 'browser translation',
            'reasoning' => false,
            'source' => 'en-US',
            'target' => 'it-IT',
        ]);
        self::assertSame(200, $response->responseStatus);
        self::assertCount(1, $response->matches);
        self::assertSame('browser translation', $response->matches[0]->raw_translation);
        self::assertSame('MT-Lara', $response->matches[0]->created_by);
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function getWithProvidedTranslationUsesThinkSuffixWhenReasoningIsNonBoolean(): void
    {
        $response = $this->engine->get([
            'segment' => 'source segment',
            'translation' => 'browser translation',
            'source' => 'en-US',
            'target' => 'it-IT',
            'reasoning' => 'yes',
            'lara_style' => 'creative',
        ]);
        self::assertSame(200, $response->responseStatus);
        self::assertSame('MT-Lara Think', $response->matches[0]->created_by);
        self::assertSame('creative', $response->matches[0]->style);
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function getWithProvidedTranslationAndScoreUsesQualityEstimationPath(): void
    {
        $fallback = $this->createMock(MMT::class);
        $fallback->expects(self::once())
            ->method('getQualityEstimation')
            ->with('en-US', 'it-IT', 'source segment', 'browser translation', '2')
            ->willReturn(0.77);
        $this->engine->setMmtFallback($fallback);
        $response = $this->engine->get([
            'segment' => 'source segment',
            'translation' => 'browser translation',
            'reasoning' => false,
            'source' => 'en-US',
            'target' => 'it-IT',
            'include_score' => true,
            'mt_qe_engine_id' => '2',
        ]);
        self::assertSame(0.77, $response->matches[0]->score);
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function getWithProvidedTranslationAndScoreUsesDefaultQeEngineIdWhenMissing(): void
    {
        $fallback = $this->createMock(MMT::class);
        $fallback->expects(self::once())
            ->method('getQualityEstimation')
            ->with('en-US', 'it-IT', 'source segment', 'browser translation', '2')
            ->willReturn(0.66);
        $this->engine->setMmtFallback($fallback);
        $response = $this->engine->get([
            'segment' => 'source segment',
            'translation' => 'browser translation',
            'reasoning' => false,
            'source' => 'en-US',
            'target' => 'it-IT',
            'include_score' => true,
        ]);
        self::assertSame(0.66, $response->matches[0]->score);
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function getWithInvalidCredentialsExceptionCodeReThrowsDomainMessage(): void
    {
        $httpClient = new TestHttpClient();
        $client = $this->createStub(LaraClient::class);
        $client->method('getHttpClient')->willReturn($httpClient);
        $client->method('translate')->willThrowException(new LaraException('auth failed', 401));
        $this->engine->setMockClient($client);
        $this->expectException(LaraException::class);
        $this->expectExceptionMessage('Lara credentials not valid');
        $this->engine->get([
            'segment' => 'source segment',
            'source' => 'en-US',
            'target' => 'it-IT',
            'keys' => ['k1'],
            'context_list_before' => [],
            'context_list_after' => [],
        ]);
    }
    #[Test]
    public function reMapKeyListPrefixesKeysWithExtMy(): void
    {
        self::assertSame(['ext_my_a', 'ext_my_b'], $this->engine->reMapKeyList(['a', 'b']));
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function memoryExistsDelegatesToClientMemoriesGet(): void
    {
        $memory = new Memory('id1', '2026-01-01', '2026-01-01', '2026-01-01', 'name', 'owner', 1, 'ext1', 'sec');
        $memories = $this->getMockBuilder(Memories::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $memories->expects(self::once())
            ->method('get')
            ->with('ext_my_abc123')
            ->willReturn($memory);
        $client = $this->createStub(LaraClient::class);
        $client->memories = $memories;
        $this->engine->setMockClient($client);
        $memoryKey = new MemoryKeyStruct();
        $memoryKey->tm_key = new TmKeyStruct(['key' => 'abc123']);
        $result = $this->engine->memoryExists($memoryKey);
        self::assertIsArray($result);
        self::assertSame('id1', $result['id']);
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function memoryExistsReturnsNullWhenClientReturnsNull(): void
    {
        $memories = $this->getMockBuilder(Memories::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $memories->expects(self::once())
            ->method('get')
            ->with('ext_my_missing')
            ->willReturn(null);
        $client = $this->createStub(LaraClient::class);
        $client->memories = $memories;
        $this->engine->setMockClient($client);
        $memoryKey = new MemoryKeyStruct();
        $memoryKey->tm_key = new TmKeyStruct(['key' => 'missing']);
        self::assertNull($this->engine->memoryExists($memoryKey));
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function memoryExistsUsesEmptyExternalSuffixWhenTmKeyIsMissing(): void
    {
        $memories = $this->getMockBuilder(Memories::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $memories->expects(self::once())
            ->method('get')
            ->with('ext_my_')
            ->willReturn(null);
        $client = $this->createStub(LaraClient::class);
        $client->memories = $memories;
        $this->engine->setMockClient($client);
        $memoryKey = new MemoryKeyStruct();
        self::assertNull($this->engine->memoryExists($memoryKey));
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function getAvailableLanguagesDelegatesToClientAndNormalizesCodes(): void
    {
        try {
            (new RedisHandler())->getConnection()->del('lara_languages');
        } catch (Throwable) {
        }
        $client = $this->createMock(LaraClient::class);
        $client->expects(self::once())
            ->method('getLanguages')
            ->willReturn(['en-US', 'it-IT', 'en-GB']);
        $this->engine->setMockClient($client);
        $languages = $this->engine->getAvailableLanguages();
        self::assertContains('en', $languages);
        self::assertContains('it', $languages);
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function getAvailableLanguagesReturnsCachedValueWithoutCallingClient(): void
    {
        try {
            (new RedisHandler())->getConnection()->setex('lara_languages', 60, serialize(['fr', 'de']));
        } catch (Throwable) {
            self::markTestSkipped('Redis not available for cache-path test');
        }
        $client = $this->createMock(LaraClient::class);
        $client->expects(self::never())->method('getLanguages');
        $this->engine->setMockClient($client);
        self::assertSame(['fr', 'de'], $this->engine->getAvailableLanguages());
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function getAvailableLanguagesReturnsEmptyArrayWhenClientHasNoLanguages(): void
    {
        try {
            (new RedisHandler())->getConnection()->del('lara_languages');
        } catch (Throwable) {
        }
        $client = $this->createMock(LaraClient::class);
        $client->expects(self::once())
            ->method('getLanguages')
            ->willReturn([]);
        $this->engine->setMockClient($client);
        self::assertSame([], $this->engine->getAvailableLanguages());
    }
    /**
     * @throws Exception
     */
    #[Test]
    public function getInternalClientReturnsInjectedHttpClient(): void
    {
        $httpClient = new TestHttpClient();
        $client = $this->createStub(LaraClient::class);
        $client->method('getHttpClient')->willReturn($httpClient);
        $this->engine->setMockClient($client);
        self::assertSame($httpClient, $this->engine->getInternalClient());
    }
    /**
     * @throws Exception
     */
    #[Test]
    public function updateReturnsTrueWhenNoKeysProvided(): void
    {
        $this->engine->setMockClient($this->createStub(LaraClient::class));
        self::assertTrue($this->engine->update([
            'keys' => [],
        ]));
    }
    /**
     * @throws Exception
     */
    #[Test]
    public function updateReturnsTrueForUrLatnPkGuard(): void
    {
        $this->engine->setMockClient($this->createStub(LaraClient::class));
        self::assertTrue($this->engine->update([
            'keys' => ['k1'],
            'target' => 'ur-Latn-PK',
        ]));
    }
    /**
     * @throws Exception
     */
    #[Test]
    public function updateSuccessDelegatesToLaraAndPrivateMmt(): void
    {
        $httpClient = new TestHttpClient();
        $memories = $this->getMockBuilder(Memories::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addTranslation'])
            ->getMock();
        $memories->expects(self::once())
            ->method('addTranslation')
            ->with(
                ['ext_my_k1'],
                'en-US',
                'it-IT',
                'source segment',
                'translated segment',
                'tuid-1',
                'before',
                'after',
                [
                    Headers::LARA_TUID_HEADER => 'tuid-1',
                    Headers::LARA_TRANSLATION_ORIGIN_HEADER => 'matecat',
                ]
            );
        $client = $this->createStub(LaraClient::class);
        $client->method('getHttpClient')->willReturn($httpClient);
        $client->memories = $memories;
        $this->engine->setMockClient($client);
        $privateMmt = $this->createMock(MMT::class);
        $privateMmt->expects(self::once())->method('update')->willReturn(true);
        $this->engine->setMmtPrivateLicense($privateMmt);
        $result = $this->engine->update([
            'keys' => ['k1'],
            'source' => 'en-US',
            'target' => 'it-IT',
            'segment' => 'source segment',
            'translation' => 'translated segment',
            'tuid' => 'tuid-1',
            'context_before' => 'before',
            'context_after' => 'after',
            'translation_origin' => 'matecat',
        ]);
        self::assertTrue($result);
        self::assertSame('ext_my_k1', $httpClient->capturedHeaders[Headers::LARA_MEMORIES_IDS] ?? null);
    }
    /**
     * @throws Exception
     */
    #[Test]
    public function updateSuccessWithoutPrivateMmtReturnsTrue(): void
    {
        $httpClient = new TestHttpClient();
        $memories = $this->getMockBuilder(Memories::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addTranslation'])
            ->getMock();
        $memories->expects(self::once())->method('addTranslation');
        $client = $this->createStub(LaraClient::class);
        $client->method('getHttpClient')->willReturn($httpClient);
        $client->memories = $memories;
        $this->engine->setMockClient($client);
        $result = $this->engine->update([
            'keys' => ['k1'],
            'source' => 'en-US',
            'target' => 'it-IT',
            'segment' => 'source segment',
            'translation' => 'translated segment',
            'tuid' => 'tuid-1',
            'context_before' => 'before',
            'context_after' => 'after',
            'translation_origin' => 'matecat',
        ]);
        self::assertTrue($result);
    }
    /**
     * @throws Exception
     */
    #[Test]
    public function updateReturnsFalseWhenPrivateMmtReturnsFalse(): void
    {
        $httpClient = new TestHttpClient();
        $memories = $this->getMockBuilder(Memories::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addTranslation'])
            ->getMock();
        $memories->expects(self::once())->method('addTranslation');
        $client = $this->createStub(LaraClient::class);
        $client->method('getHttpClient')->willReturn($httpClient);
        $client->memories = $memories;
        $this->engine->setMockClient($client);
        $privateMmt = $this->createMock(MMT::class);
        $privateMmt->expects(self::once())->method('update')->willReturn(false);
        $this->engine->setMmtPrivateLicense($privateMmt);
        $result = $this->engine->update([
            'keys' => ['k1'],
            'source' => 'en-US',
            'target' => 'it-IT',
            'segment' => 'source segment',
            'translation' => 'translated segment',
            'tuid' => 'tuid-1',
            'context_before' => 'before',
            'context_after' => 'after',
            'translation_origin' => 'matecat',
        ]);
        self::assertFalse($result);
    }
    /**
     * @throws Exception
     */
    #[Test]
    public function updateReturnsFalseWhenLaraAddTranslationThrows(): void
    {
        $httpClient = new TestHttpClient();
        $memories = $this->getMockBuilder(Memories::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addTranslation'])
            ->getMock();
        $memories->expects(self::once())
            ->method('addTranslation')
            ->willThrowException(new Exception('network failure'));
        $client = $this->createStub(LaraClient::class);
        $client->method('getHttpClient')->willReturn($httpClient);
        $client->memories = $memories;
        $this->engine->setMockClient($client);
        $result = $this->engine->update([
            'keys' => ['k1'],
            'source' => 'en-US',
            'target' => 'it-IT',
            'segment' => 'source segment',
            'translation' => 'translated segment',
            'tuid' => 'tuid-1',
            'context_before' => 'before',
            'context_after' => 'after',
            'translation_origin' => 'matecat',
        ]);
        self::assertFalse($result);
    }
    #[Test]
    public function headersAndHeaderFieldExposeExpectedValues(): void
    {
        $field = new HeaderField('X-Test', 'value');
        self::assertSame('X-Test', $field->getKey());
        self::assertSame('value', $field->getValue());
        self::assertSame(['X-Test' => 'value'], $field->getArrayCopy());
        $headers = new Headers('tuid-1', 'origin-1');
        self::assertSame('tuid-1', $headers->getTuid()?->getValue());
        self::assertSame('origin-1', $headers->getTranslationOrigin()?->getValue());
        $headers->setTuid('tuid-2')->setTranslationOrigin('origin-2');
        self::assertSame([
            Headers::LARA_TUID_HEADER => 'tuid-2',
            Headers::LARA_TRANSLATION_ORIGIN_HEADER => 'origin-2',
        ], $headers->getArrayCopy());
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function getGlossariesReturnsFromClientAndSupportsEmptyList(): void
    {
        $glossaries = $this->getMockBuilder(Glossaries::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAll'])
            ->getMock();
        $glossaries->expects(self::once())->method('getAll')->willReturn([]);
        $client = $this->createStub(LaraClient::class);
        $client->glossaries = $glossaries;
        $this->engine->setMockClient($client);
        self::assertSame([], $this->engine->getGlossaries());
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function getGlossariesReturnsNonEmptyListFromClient(): void
    {
        $item = new stdClass();
        $item->id = 'g1';
        $glossaries = $this->getMockBuilder(Glossaries::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAll'])
            ->getMock();
        $glossaries->expects(self::once())->method('getAll')->willReturn([$item]);
        $client = $this->createStub(LaraClient::class);
        $client->glossaries = $glossaries;
        $this->engine->setMockClient($client);
        $result = $this->engine->getGlossaries();
        self::assertCount(1, $result);
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function getGlossariesReturnsEmptyWhenClientGlossariesIsEmpty(): void
    {
        $client = $this->createStub(LaraClient::class);
        $client->glossaries = null;
        $this->engine->setMockClient($client);
        self::assertSame([], $this->engine->getGlossaries());
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function deleteMemoryReturnsSerializedResponse(): void
    {
        $deleted = new Memory('id-del', '2026-01-01', '2026-01-01', '2026-01-01', 'name', 'owner', 1, 'ext', 'sec');
        $memories = $this->getMockBuilder(Memories::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['delete'])
            ->getMock();
        $memories->expects(self::once())->method('delete')->with('mem-1')->willReturn($deleted);
        $client = $this->createStub(LaraClient::class);
        $client->memories = $memories;
        $this->engine->setMockClient($client);
        $result = $this->engine->deleteMemory(['id' => 'mem-1', 'externalId' => 'ext_my_abc']);
        self::assertSame('id-del', $result['id'] ?? null);
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function deleteMemoryReturnsEmptyArrayOnNotFound(): void
    {
        $memories = $this->getMockBuilder(Memories::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['delete'])
            ->getMock();
        $memories->expects(self::once())
            ->method('delete')
            ->willThrowException(new LaraApiException(404, 'NotFound', 'no memory'));
        $client = $this->createStub(LaraClient::class);
        $client->memories = $memories;
        $this->engine->setMockClient($client);
        self::assertSame([], $this->engine->deleteMemory(['id' => 'missing', 'externalId' => 'ext_my_missing']));
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function deleteMemoryRethrowsNon404ApiException(): void
    {
        $memories = $this->getMockBuilder(Memories::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['delete'])
            ->getMock();
        $memories->expects(self::once())
            ->method('delete')
            ->willThrowException(new LaraApiException(500, 'ServerError', 'boom'));
        $client = $this->createStub(LaraClient::class);
        $client->memories = $memories;
        $this->engine->setMockClient($client);
        $this->expectException(LaraApiException::class);
        $this->engine->deleteMemory(['id' => 'fail', 'externalId' => 'ext_my_fail']);
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function deleteMemoryWithPrivateMmtDeletesBothSides(): void
    {
        $deleted = new Memory('id-del', '2026-01-01', '2026-01-01', '2026-01-01', 'name', 'owner', 1, 'ext', 'sec');
        $memories = $this->getMockBuilder(Memories::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['delete'])
            ->getMock();
        $memories->expects(self::once())->method('delete')->willReturn($deleted);
        $client = $this->createStub(LaraClient::class);
        $client->memories = $memories;
        $this->engine->setMockClient($client);
        $privateMmt = $this->createMock(MMT::class);
        $privateMmt->expects(self::once())->method('getMemoryIfMine')->willReturn(['id' => 1]);
        $privateMmt->expects(self::once())->method('deleteMemory');
        $this->engine->setMmtPrivateLicense($privateMmt);
        $this->engine->deleteMemory(['id' => 'mem-1', 'externalId' => 'ext_my_abc']);
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function deleteMemoryWithPrivateMmtSkipsDeleteWhenMemoryIsMissingInMmt(): void
    {
        $deleted = new Memory('id-del', '2026-01-01', '2026-01-01', '2026-01-01', 'name', 'owner', 1, 'ext', 'sec');
        $memories = $this->getMockBuilder(Memories::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['delete'])
            ->getMock();
        $memories->expects(self::once())->method('delete')->willReturn($deleted);
        $client = $this->createStub(LaraClient::class);
        $client->memories = $memories;
        $this->engine->setMockClient($client);
        $privateMmt = $this->createMock(MMT::class);
        $privateMmt->expects(self::once())->method('getMemoryIfMine')->willReturn([]);
        $privateMmt->expects(self::never())->method('deleteMemory');
        $this->engine->setMmtPrivateLicense($privateMmt);
        $this->engine->deleteMemory(['id' => 'mem-1', 'externalId' => 'ext_my_abc']);
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function getMemoryIfMineAliasesMemoryExists(): void
    {
        $memory = new Memory('id1', '2026-01-01', '2026-01-01', '2026-01-01', 'name', 'owner', 1, 'ext1', 'sec');
        $memories = $this->getMockBuilder(Memories::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $memories->expects(self::once())->method('get')->willReturn($memory);
        $client = $this->createStub(LaraClient::class);
        $client->memories = $memories;
        $this->engine->setMockClient($client);
        $memoryKey = new MemoryKeyStruct();
        $memoryKey->tm_key = new TmKeyStruct(['key' => 'abc123']);
        $result = $this->engine->getMemoryIfMine($memoryKey);
        self::assertSame('id1', $result['id'] ?? null);
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function importMemoryImportsToLaraAndPrivateMmt(): void
    {
        $memory = new Memory('id1', '2026-01-01', '2026-01-01', '2026-01-01', 'name', 'owner', 1, 'ext1', 'sec');
        $memories = $this->getMockBuilder(Memories::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'importTmx'])
            ->getMock();
        $memories->expects(self::once())->method('get')->with('ext_my_key1')->willReturn($memory);
        $memories->expects(self::once())->method('importTmx')
            ->with(
                'ext_my_key1',
                self::callback(static fn (string $path): bool => str_ends_with($path, '.gz') && is_file($path)),
                true
            )
            ->willReturn(new stdClass());
        $client = $this->createStub(LaraClient::class);
        $client->memories = $memories;
        $this->engine->setMockClient($client);
        $privateMmt = $this->createMock(MMT::class);
        $privateMmt->expects(self::once())->method('importMemory');
        $this->engine->setMmtPrivateLicense($privateMmt);
        $tmp = tempnam(sys_get_temp_dir(), 'lara_');
        self::assertIsString($tmp);
        file_put_contents($tmp, "<tmx>test</tmx>\n");
        $user = UserStruct::getStruct();
        $this->engine->importMemory($tmp, 'key1', $user);
        @unlink($tmp);
        @unlink($tmp . '.gz');
    }
    /**
     * @throws LaraException
     * @throws Exception
     */
    #[Test]
    public function importMemoryThrowsWhenLaraMemoryDoesNotExist(): void
    {
        $memories = $this->getMockBuilder(Memories::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $memories->expects(self::once())->method('get')->willReturn(null);
        $client = $this->createStub(LaraClient::class);
        $client->memories = $memories;
        $this->engine->setMockClient($client);
        $tmp = tempnam(sys_get_temp_dir(), 'lara_');
        self::assertIsString($tmp);
        file_put_contents($tmp, "<tmx>test</tmx>\n");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('404 not found');
        try {
            $this->engine->importMemory($tmp, 'missing', UserStruct::getStruct());
        } finally {
            @unlink($tmp);
            @unlink($tmp . '.gz');
        }
    }
    #[Test]
    public function deleteAlwaysReturnsTrue(): void
    {
        self::assertTrue($this->engine->delete([]));
    }
    #[Test]
    public function setIsNoOpAndReturnsNull(): void
    {
        self::assertNull($this->engine->set([]));
    }
    #[Test]
    public function decodeWrapperReturnsEmptyArray(): void
    {
        self::assertSame([], $this->engine->decodeForTest('raw'));
    }
    #[Test]
    public function reMapKeyListReturnsEmptyArrayWhenInputIsEmpty(): void
    {
        self::assertSame([], $this->engine->reMapKeyList());
    }
    #[Test]
    public function syncMemoriesHandlesMissingProjectGracefully(): void
    {
        $this->engine->syncMemories([
            'id' => -1,
            'id_customer' => 'nobody@example.invalid',
        ]);
        self::assertTrue(true);
    }
    /**
     * @throws Exception
     */
    #[Test]
    public function staticConfigurationMethodsBehaveAsExpected(): void
    {
        self::assertSame(['enable_mt_analysis', 'lara_style', 'lara_glossaries'], Lara::getConfigurationParameters());
        self::assertSame('faithful', Lara::validateLaraStyle('faithful'));
        $this->expectException(InvalidArgumentException::class);
        Lara::validateLaraStyle('invalid-style');
    }
}
class TestHttpClient extends HttpClient implements HttpClientInterface
{
    /** @var array<string, string> */
    public array $capturedHeaders = [];
    public function __construct()
    {
        parent::__construct('https://example.test', new AccessKey('id', 'secret'));
    }
    public function authenticate(): string
    {
        return 'token';
    }
    public function setExtraHeader($name, $value): void
    {
        $this->capturedHeaders[(string)$name] = (string)$value;
    }
}
class TestLara extends Lara
{
    private LaraClient $mockClient;
    public function setMockClient(LaraClient $mockClient): void
    {
        $this->mockClient = $mockClient;
    }
    public function setMmtFallback(MMT $fallback): void
    {
        $prop = new ReflectionProperty(Lara::class, 'mmt_GET_Fallback');
        $prop->setValue($this, $fallback);
    }
    public function setMmtPrivateLicense(MMT $private): void
    {
        $prop = new ReflectionProperty(Lara::class, 'mmt_SET_PrivateLicense');
        $prop->setValue($this, $private);
    }
    protected function _getClient(): LaraClient
    {
        return $this->mockClient;
    }
    /**
     * @return array<string, mixed>
     */
    public function decodeForTest(mixed $rawValue): array
    {
        return $this->_decode($rawValue);
    }
}
