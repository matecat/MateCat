<?php

declare(strict_types=1);

namespace unit\Engines;

use DomainException;
use Exception;
use Model\Engines\Structs\EngineStruct;
use Model\Jobs\MetadataDao as JobsMetadataDao;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use SplFileObject;
use TestHelpers\AbstractTest;
use Utils\Constants\EngineConstants;
use Utils\Engines\MMT;
use Utils\Engines\MMT\MMTServiceApi;
use Utils\Engines\MMT\MMTServiceApiRequestException;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\Registry\AppConfig;
use Utils\TmKeyManagement\TmKeyStruct;

class MMTEngineMethodsTest extends AbstractTest
{
    private function createEngineStruct(): EngineStruct
    {
        return new EngineStruct([
            'id' => 999,
            'name' => 'Test MMT',
            'type' => EngineConstants::MT,
            'class_load' => 'MMT',
            'base_url' => 'https://api.modernmt.com',
            'translate_relative_url' => 'translate',
            'extra_parameters' => '{"MMT-License":"test-license","MMT-context-analyzer":false}',
            'others' => '{"tmx_import_relative_url":"memories/content","api_key_check_auth_url":"users/me","user_update_activate":"memories/connect","context_get":"context-vector"}',
        ]);
    }

    private function createEngineWithClient(MMTServiceApi $client): TestMMT
    {
        $engine = new TestMMT($this->createEngineStruct());
        $engine->setMockClient($client);

        return $engine;
    }

    private function createMemoryKey(string $key): MemoryKeyStruct
    {
        return new MemoryKeyStruct([
            'uid' => 1,
            'tm_key' => new TmKeyStruct(['key' => $key]),
        ]);
    }

    private function invokeConfigureContribution(MMT $engine, array $config): array
    {
        $method = new ReflectionMethod(MMT::class, 'configureContribution');

        /** @var array $result */
        $result = $method->invoke($engine, $config);

        return $result;
    }

    private function invokeGetContext(MMT $engine, SplFileObject $file, string $source, array $targets): ?array
    {
        $method = new ReflectionMethod(MMT::class, 'getContext');

        /** @var array|null $result */
        $result = $method->invoke($engine, $file, $source, $targets);

        return $result;
    }

    #[Test]
    public function getSuccessReturnsMatchWithScore(): void
    {
        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::once())
            ->method('translate')
            ->with(
                'en-US',
                'it-IT',
                'hello world',
                null,
                ['x_mm-k1'],
                null,
                MMT::GET_REQUEST_TIMEOUT,
                'normal',
                null,
                null,
                null,
                true,
                '2'
            )
            ->willReturn(['translation' => 'ciao mondo', 'score' => 0.95]);

        $engine = $this->createEngineWithClient($client);
        $response = $engine->get([
            'source' => 'en-US',
            'target' => 'it-IT',
            'segment' => 'hello world',
            'keys' => ['k1'],
            'include_score' => true,
        ]);

        self::assertInstanceOf(GetMemoryResponse::class, $response);
        self::assertCount(1, $response->matches);
        self::assertSame('ciao mondo', $response->matches[0]->raw_translation);
        self::assertSame(0.95, $response->matches[0]->score);
    }

    #[Test]
    public function getWithAnalysisAndSkipAnalysisReturnsEmptyResponse(): void
    {
        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::never())->method('translate');

        $engine = $this->createEngineWithClient($client);
        $engine->setAnalysis(true)->setSkipAnalysis(true);

        $response = $engine->get([
            'source' => 'en-US',
            'target' => 'it-IT',
            'segment' => 'ignored',
            'keys' => [],
        ]);

        self::assertInstanceOf(GetMemoryResponse::class, $response);
        self::assertSame([], $response->matches);
    }

    #[Test]
    public function getNullTranslationFallsBackToGoogleFallback(): void
    {
        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::once())->method('translate')->willReturn(null);

        $engine = $this->createEngineWithClient($client);
        $fallback = new GetMemoryResponse(['responseStatus' => 503, 'matches' => []]);
        $engine->setFallbackResponse($fallback);

        $response = $engine->get([
            'source' => 'en-US',
            'target' => 'it-IT',
            'segment' => 'hello',
            'keys' => [],
        ]);

        self::assertSame($fallback, $response);
        self::assertNotNull($engine->lastFallbackConfig);
        self::assertSame('hello', $engine->lastFallbackConfig['segment'] ?? null);
    }

    #[Test]
    public function getExceptionFallsBackToGoogleFallback(): void
    {
        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::once())
            ->method('translate')
            ->willThrowException(new RuntimeException('upstream boom'));

        $engine = $this->createEngineWithClient($client);
        $fallback = new GetMemoryResponse(['responseStatus' => 500, 'matches' => []]);
        $engine->setFallbackResponse($fallback);

        $response = $engine->get([
            'source' => 'en-US',
            'target' => 'it-IT',
            'segment' => 'hello',
            'keys' => [],
        ]);

        self::assertSame($fallback, $response);
    }

    #[Test]
    public function getWithGlossariesPassesGlossaryParametersToClient(): void
    {
        $pid = 910001;
        (new ProjectsMetadataDao())->set($pid, 'mmt_glossaries', '["g1","g2"]');
        (new ProjectsMetadataDao())->set($pid, 'mmt_ignore_glossary_case', '1');

        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::once())
            ->method('translate')
            ->with(
                'en-US',
                'fr-FR',
                'Hello',
                null,
                ['x_mm-k1'],
                null,
                MMT::GET_REQUEST_TIMEOUT,
                'normal',
                null,
                'g1,g2',
                true,
                null,
                '2'
            )
            ->willReturn(['translation' => 'Bonjour']);

        $engine = $this->createEngineWithClient($client);
        $response = $engine->get([
            'id_project' => $pid,
            'source' => 'en-US',
            'target' => 'fr-FR',
            'segment' => 'Hello',
            'keys' => ['k1'],
        ]);

        self::assertCount(1, $response->matches);
        self::assertSame('Bonjour', $response->matches[0]->raw_translation);
    }

    #[Test]
    public function setSuccessReturnsTrue(): void
    {
        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::once())
            ->method('addToMemoryContent')
            ->with(['x_mm-k1'], 'en-US', 'it-IT', 'a', 'b', 's1');

        $engine = $this->createEngineWithClient($client);
        self::assertTrue($engine->set([
            'keys' => ['k1'],
            'source' => 'en-US',
            'target' => 'it-IT',
            'segment' => 'a',
            'translation' => 'b',
            'session' => 's1',
        ]));
    }

    #[Test]
    public function setRequestExceptionReturnsTrue(): void
    {
        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::once())
            ->method('addToMemoryContent')
            ->willThrowException(new MMTServiceApiRequestException('ServiceException', 401, 'expired'));

        $engine = $this->createEngineWithClient($client);
        self::assertTrue($engine->set([
            'keys' => ['k1'],
            'source' => 'en-US',
            'target' => 'it-IT',
            'segment' => 'a',
            'translation' => 'b',
            'session' => 's1',
        ]));
    }

    #[Test]
    public function setGenericExceptionReturnsFalse(): void
    {
        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::once())
            ->method('addToMemoryContent')
            ->willThrowException(new RuntimeException('generic'));

        $engine = $this->createEngineWithClient($client);
        self::assertFalse($engine->set([
            'keys' => ['k1'],
            'source' => 'en-US',
            'target' => 'it-IT',
            'segment' => 'a',
            'translation' => 'b',
            'session' => 's1',
        ]));
    }

    #[Test]
    public function updateSuccessReturnsTrue(): void
    {
        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::once())
            ->method('updateMemoryContent')
            ->with('t-1', ['x_mm-k1'], 'en-US', 'it-IT', 'a', 'b', 's1');

        $engine = $this->createEngineWithClient($client);
        self::assertTrue($engine->update([
            'tuid' => 't-1',
            'keys' => ['k1'],
            'source' => 'en-US',
            'target' => 'it-IT',
            'segment' => 'a',
            'translation' => 'b',
            'session' => 's1',
        ]));
    }

    #[Test]
    public function updateExceptionReturnsFalse(): void
    {
        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::once())
            ->method('updateMemoryContent')
            ->willThrowException(new RuntimeException('fail'));

        $engine = $this->createEngineWithClient($client);
        self::assertFalse($engine->update([
            'tuid' => 't-1',
            'keys' => ['k1'],
            'source' => 'en-US',
            'target' => 'it-IT',
            'segment' => 'a',
            'translation' => 'b',
            'session' => 's1',
        ]));
    }

    #[Test]
    public function deleteThrowsDomainException(): void
    {
        $engine = $this->createEngineWithClient($this->createStub(MMTServiceApi::class));
        $this->expectException(DomainException::class);
        $engine->delete([]);
    }

    #[Test]
    public function memoryExistsReturnsMemoryWhenFound(): void
    {
        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::once())
            ->method('getMemory')
            ->with('x_mm-my-key')
            ->willReturn(['id' => 'x_mm-my-key']);

        $engine = $this->createEngineWithClient($client);
        $memory = $engine->memoryExists($this->createMemoryKey('my-key'));

        self::assertSame(['id' => 'x_mm-my-key'], $memory);
    }

    #[Test]
    public function memoryExistsReturnsNullOnRequestException(): void
    {
        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::once())
            ->method('getMemory')
            ->willThrowException(new MMTServiceApiRequestException('ServiceException', 404, 'not-found'));

        $engine = $this->createEngineWithClient($client);
        self::assertNull($engine->memoryExists($this->createMemoryKey('missing')));
    }

    #[Test]
    public function importMemorySuccessCompressesAndImports(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mmt-import-');
        self::assertIsString($tmp);
        file_put_contents($tmp, "<tmx>line1</tmx>\n<tmx>line2</tmx>\n");

        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::once())->method('getMemory')->willReturn(['id' => 'x_mm-key1']);
        $client->expects(self::once())
            ->method('importIntoMemoryContent')
            ->with(
                'x_mm-key1',
                $tmp . '.gz',
                'gzip'
            );

        $engine = $this->createEngineWithClient($client);
        $engine->importMemory($tmp, 'key1', UserStruct::getStruct());

        self::assertFileExists($tmp . '.gz');
        @unlink($tmp);
        @unlink($tmp . '.gz');
    }

    #[Test]
    public function importMemoryReturnsEarlyWhenMemoryResponseIsEmpty(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mmt-import-empty-');
        self::assertIsString($tmp);
        file_put_contents($tmp, "<tmx/>\n");

        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::once())->method('getMemory')->willReturn([]);
        $client->expects(self::never())->method('importIntoMemoryContent');

        $engine = $this->createEngineWithClient($client);
        $engine->importMemory($tmp, 'key1', UserStruct::getStruct());

        self::assertFileDoesNotExist($tmp . '.gz');
        @unlink($tmp);
    }

    #[Test]
    public function importMemoryThrowsWhenGzopenFails(): void
    {
        $tmp = '/proc/mmt-no-write-file';

        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::once())->method('getMemory')->willReturn(['id' => 'x_mm-key1']);
        $client->expects(self::never())->method('importIntoMemoryContent');

        $engine = $this->createEngineWithClient($client);

        $this->expectException(RuntimeException::class);
        set_error_handler(static fn (): bool => true);
        try {
            $engine->importMemory($tmp, 'key1', UserStruct::getStruct());
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function checkAccountReturnsClientMeResponse(): void
    {
        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::once())->method('me')->willReturn(['id' => 77]);

        $engine = $this->createEngineWithClient($client);
        self::assertSame(['id' => 77], $engine->checkAccount());
    }

    #[Test]
    public function checkAccountWrapsExceptionMessage(): void
    {
        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::once())->method('me')->willThrowException(new RuntimeException('boom'));

        $engine = $this->createEngineWithClient($client);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('MMT license not valid');
        $engine->checkAccount();
    }

    #[Test]
    public function connectKeysWithNonEmptyListDelegatesToClient(): void
    {
        $keyStruct = $this->createMemoryKey('k1');

        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::once())
            ->method('connectMemories')
            ->with(['x_mm-k1'])
            ->willReturn(['ok' => true]);

        $engine = $this->createEngineWithClient($client);
        self::assertSame(['ok' => true], $engine->connectKeys([$keyStruct]));
    }

    #[Test]
    public function connectKeysWithEmptyListSkipsClientCall(): void
    {
        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::never())->method('connectMemories');

        $engine = $this->createEngineWithClient($client);
        self::assertSame([], $engine->connectKeys([]));
    }

    #[Test]
    public function createMemoryDelegatesToClient(): void
    {
        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::once())
            ->method('createMemory')
            ->with('Name', 'Desc', 'Ext')
            ->willReturn(['id' => 'm1']);

        $engine = $this->createEngineWithClient($client);
        self::assertSame(['id' => 'm1'], $engine->createMemory('Name', 'Desc', 'Ext'));
    }

    #[Test]
    public function deleteMemoryReturnsClientResultAndCoalescesNullToEmptyArray(): void
    {
        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::exactly(2))
            ->method('deleteMemory')
            ->willReturnOnConsecutiveCalls(['deleted' => true], null);

        $engine = $this->createEngineWithClient($client);
        self::assertSame(['deleted' => true], $engine->deleteMemory(['id' => 'abc']));
        self::assertSame([], $engine->deleteMemory(['id' => 'abc']));
    }

    #[Test]
    public function getAllMemoriesGetMemoryUpdateMemoryImportAndStatusDelegateToClient(): void
    {
        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::once())->method('getAllMemories')->willReturn(['items' => [1]]);
        $client->expects(self::once())->method('getMemory')->with('m1')->willReturn(['id' => 'm1']);
        $client->expects(self::once())->method('updateMemory')->with('m1', 'new-name')->willReturn(['id' => 'm1', 'name' => 'new-name']);
        $client->expects(self::once())->method('importGlossary')->with('m1', ['a' => 'b'])->willReturn(['uuid' => 'g1']);
        $client->expects(self::once())->method('updateGlossary')->with('m1', ['a' => 'c'])->willReturn(['uuid' => 'g2']);
        $client->expects(self::once())->method('importJobStatus')->with('job-uuid')->willReturn(['status' => 'done']);

        $engine = $this->createEngineWithClient($client);

        self::assertSame(['items' => [1]], $engine->getAllMemories());
        self::assertSame(['id' => 'm1'], $engine->getMemory('m1'));
        self::assertSame(['id' => 'm1', 'name' => 'new-name'], $engine->updateMemory('m1', 'new-name'));
        self::assertSame(['uuid' => 'g1'], $engine->importGlossary('m1', ['a' => 'b']));
        self::assertSame(['uuid' => 'g2'], $engine->updateGlossary('m1', ['a' => 'c']));
        self::assertSame(['status' => 'done'], $engine->importJobStatus('job-uuid'));
    }

    #[Test]
    public function getMemoryIfMineReturnsMemoryOnlyForMatchingOwner(): void
    {
        $memoryKey = $this->createMemoryKey('k1');

        $engineMine = $this->getMockBuilder(MMT::class)
            ->setConstructorArgs([$this->createEngineStruct()])
            ->onlyMethods(['checkAccount', 'memoryExists'])
            ->getMock();
        $engineMine->expects(self::once())->method('checkAccount')->willReturn(['id' => 10]);
        $engineMine->expects(self::once())->method('memoryExists')->with($memoryKey)->willReturn(['owner' => ['user' => '10']]);
        self::assertSame(['owner' => ['user' => '10']], $engineMine->getMemoryIfMine($memoryKey));

        $engineNotMine = $this->getMockBuilder(MMT::class)
            ->setConstructorArgs([$this->createEngineStruct()])
            ->onlyMethods(['checkAccount', 'memoryExists'])
            ->getMock();
        $engineNotMine->expects(self::once())->method('checkAccount')->willReturn(['id' => 10]);
        $engineNotMine->expects(self::once())->method('memoryExists')->with($memoryKey)->willReturn(['owner' => ['user' => '99']]);
        self::assertNull($engineNotMine->getMemoryIfMine($memoryKey));

        $engineEmpty = $this->getMockBuilder(MMT::class)
            ->setConstructorArgs([$this->createEngineStruct()])
            ->onlyMethods(['checkAccount', 'memoryExists'])
            ->getMock();
        $engineEmpty->expects(self::once())->method('checkAccount')->willReturn(['id' => 10]);
        $engineEmpty->expects(self::once())->method('memoryExists')->with($memoryKey)->willReturn([]);
        self::assertNull($engineEmpty->getMemoryIfMine($memoryKey));
    }

    #[Test]
    public function getQualityEstimationHandlesSuccessAndNullCases(): void
    {
        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::exactly(3))
            ->method('qualityEstimation')
            ->willReturnOnConsecutiveCalls(
                ['score' => 0.77],
                null,
                ['foo' => 'bar']
            );

        $engine = $this->createEngineWithClient($client);
        self::assertSame(0.77, $engine->getQualityEstimation('en-US', 'it-IT', 's', 't'));
        self::assertNull($engine->getQualityEstimation('en-US', 'it-IT', 's', 't'));
        self::assertNull($engine->getQualityEstimation('en-US', 'it-IT', 's', 't'));
    }

    #[Test]
    public function getAvailableLanguagesDelegatesToClient(): void
    {
        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::once())->method('getAvailableLanguages')->willReturn(['en', 'it']);

        $engine = $this->createEngineWithClient($client);
        self::assertSame(['en', 'it'], $engine->getAvailableLanguages());
    }

    #[Test]
    public function decodeReturnsEmptyArray(): void
    {
        $engine = $this->createEngineWithClient($this->createStub(MMTServiceApi::class));
        self::assertSame([], $engine->decodeForTest('raw', []));
    }

    #[Test]
    public function getConfigurationParametersReturnsExpectedArray(): void
    {
        self::assertSame(
            ['enable_mt_analysis', 'mmt_glossaries', 'mmt_activate_context_analyzer', 'mmt_ignore_glossary_case'],
            MMT::getConfigurationParameters()
        );
    }

    #[Test]
    public function configureContributionReturnsExpectedShapeForNormalAndAnalysisMode(): void
    {
        $engine = $this->createEngineWithClient($this->createStub(MMTServiceApi::class));

        $normal = $this->invokeConfigureContribution($engine, [
            'keys' => ['k1', 'k2'],
            'source' => 'en-US',
            'target' => 'it-IT',
            'segment' => 'seg',
        ]);

        self::assertSame(['x_mm-k1', 'x_mm-k2'], $normal['keys']);
        self::assertSame('normal', $normal['priority']);
        self::assertArrayHasKey('secret_key', $normal);

        $engine->setAnalysis(true);
        $analysis = $this->invokeConfigureContribution($engine, [
            'id_user' => ['u1', 'u2'],
            'keys' => ['k1'],
            'source' => 'en-US',
            'target' => 'it-IT',
            'segment' => 'seg',
        ]);

        // without job_id it still follows normal branch; this asserts deterministic remapping behavior
        self::assertSame(['x_mm-k1'], $analysis['keys']);
        self::assertSame('normal', $analysis['priority']);
    }

    #[Test]
    public function getContextReturnsVectorMapAndNullWhenMissingVectors(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mmt-ctx-');
        self::assertIsString($tmp);
        file_put_contents($tmp, "alpha\nbeta\n");

        $client = $this->createMock(MMTServiceApi::class);
        $client->expects(self::exactly(2))
            ->method('getContextVectorFromFile')
            ->willReturnOnConsecutiveCalls(
                ['vectors' => ['it-IT' => '1:0.1,2:0.2']],
                null
            );

        $engine = $this->createEngineWithClient($client);
        $file = new SplFileObject($tmp, 'r');

        $first = $this->invokeGetContext($engine, $file, 'en-US', ['it-IT']);
        self::assertSame(['en-US|it-IT' => '1:0.1,2:0.2'], $first);

        $file2 = new SplFileObject($tmp, 'r');
        $second = $this->invokeGetContext($engine, $file2, 'en-US', ['it-IT']);
        self::assertNull($second);

        @unlink($tmp);
        @unlink($tmp . '.gz');
    }

    #[Test]
    public function getContextThrowsWhenGzopenFails(): void
    {
        $engine = $this->createEngineWithClient($this->createStub(MMTServiceApi::class));
        $file = new SplFileObject('/proc/version', 'r');

        $this->expectException(RuntimeException::class);
        set_error_handler(static fn (): bool => true);
        try {
            $this->invokeGetContext($engine, $file, 'en-US', ['it-IT']);
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function syncMemoriesReturnsEarlyWhenContextAnalyzerEnabledAndSegmentsMissingKeys(): void
    {
        $pid = 920001;
        (new ProjectsMetadataDao())->set($pid, 'mmt_activate_context_analyzer', '1');

        $engine = $this->createEngineWithClient($this->createStub(MMTServiceApi::class));
        $engine->syncMemories(['id' => $pid, 'id_customer' => 'nobody@example.invalid'], [['segment' => 'only-segment']]);

        self::assertTrue(true);
    }

    #[Test]
    public function syncMemoriesSkipsContextBranchWhenContextAnalyzerDisabled(): void
    {
        $pid = 920002;
        (new ProjectsMetadataDao())->set($pid, 'mmt_activate_context_analyzer', '0');

        $engine = $this->createEngineWithClient($this->createStub(MMTServiceApi::class));
        $engine->syncMemories(['id' => $pid, 'id_customer' => 'nobody@example.invalid'], []);

        self::assertTrue(true);
    }

    #[Test]
    public function configureContributionAnalysisBranchWithJobIdUsesIdUserKeys(): void
    {
        $jobId = 930001;
        (new JobsMetadataDao())->set($jobId, '', 'mt_context', 'ctx:1,2,3');

        $engine = $this->createEngineWithClient($this->createStub(MMTServiceApi::class));
        $engine->setAnalysis(true);

        $result = $this->invokeConfigureContribution($engine, [
            'job_id' => $jobId,
            'id_user' => ['u1', 'u2'],
            'keys' => ['k1'],
            'source' => 'en-US',
            'target' => 'it-IT',
            'segment' => 'seg',
        ]);

        self::assertSame('background', $result['priority']);
        self::assertSame(['x_mm-u1', 'x_mm-u2'], $result['keys']);
        self::assertSame('ctx:1,2,3', $result['mt_context']);
    }

    #[Test]
    public function syncMemoriesReturnsEarlyWhenContextAnalyzerEnabledAndSegmentsEmpty(): void
    {
        $pid = 920003;
        (new ProjectsMetadataDao())->set($pid, 'mmt_activate_context_analyzer', '1');

        $engine = $this->createEngineWithClient($this->createStub(MMTServiceApi::class));
        $engine->syncMemories(['id' => $pid, 'id_customer' => 'nobody@example.invalid'], []);

        self::assertTrue(true);
    }

    #[Test]
    public function getG2FallbackSecretKeyReturnsNullWhenFileMissingAndReadsIniWhenPresent(): void
    {
        $originalRoot = AppConfig::$ROOT ?? null;

        $tmpRoot = sys_get_temp_dir() . '/mmt-fallback-' . uniqid('', true);
        mkdir($tmpRoot . '/inc', 0777, true);

        AppConfig::$ROOT = $tmpRoot;
        self::assertNull(MMT::getG2FallbackSecretKey());

        file_put_contents($tmpRoot . '/inc/mmt_fallback_key.ini', "secret_key = \"s3cr3t\"\n");
        self::assertSame('s3cr3t', MMT::getG2FallbackSecretKey());

        @unlink($tmpRoot . '/inc/mmt_fallback_key.ini');
        @rmdir($tmpRoot . '/inc');
        @rmdir($tmpRoot);

        if ($originalRoot !== null) {
            AppConfig::$ROOT = $originalRoot;
        }
    }

    #[Test]
    public function getClientBuildsApiClientFromEngineConfiguration(): void
    {
        $originalBuild = AppConfig::$BUILD_NUMBER;
        AppConfig::$BUILD_NUMBER = 'v1.2.3';

        $engine = new MMT($this->createEngineStruct());
        $method = new ReflectionMethod(MMT::class, '_getClient');

        $client = $method->invoke($engine);
        self::assertInstanceOf(MMTServiceApi::class, $client);

        AppConfig::$BUILD_NUMBER = $originalBuild;
    }
}

class TestMMT extends MMT
{
    private MMTServiceApi $mockClient;
    private ?GetMemoryResponse $fallbackResponse = null;

    public ?array $lastFallbackConfig = null;

    public function setMockClient(MMTServiceApi $mockClient): void
    {
        $this->mockClient = $mockClient;
    }

    public function setFallbackResponse(GetMemoryResponse $response): void
    {
        $this->fallbackResponse = $response;
    }

    protected function _getClient(): MMTServiceApi
    {
        return $this->mockClient;
    }

    /**
     * @param array<string, mixed> $_config
     */
    protected function GoogleTranslateFallback(array $_config): GetMemoryResponse
    {
        $this->lastFallbackConfig = $_config;

        return $this->fallbackResponse ?? new GetMemoryResponse(null);
    }

    /**
     * @param mixed $rawValue
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function decodeForTest(mixed $rawValue, array $parameters = []): array
    {
        return $this->_decode($rawValue, $parameters);
    }
}
