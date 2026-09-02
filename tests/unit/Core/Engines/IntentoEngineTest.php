<?php

declare(strict_types=1);

namespace Utils\Engines {

    final class IntentoCurlMock
    {
        /** @var array<string, string|false|null> */
        public static array $responsesByUrl = [];

        /** @var list<int> */
        public static array $sleepCalls = [];

        public static function reset(): void
        {
            self::$responsesByUrl = [];
            self::$sleepCalls = [];
        }
    }

    function curl_init(?string $url = null): string
    {
        return (string)$url;
    }

    /**
     * @param array<int, mixed> $options
     */
    function curl_setopt_array(string $curlHandle, array $options): bool
    {
        unset($options, $curlHandle);

        return true;
    }

    function curl_exec(string $curlHandle): string|false|null
    {
        return IntentoCurlMock::$responsesByUrl[$curlHandle] ?? null;
    }

    function curl_close(string $curlHandle): void
    {
        unset($curlHandle);
    }

    function sleep(int $seconds): int
    {
        IntentoCurlMock::$sleepCalls[] = $seconds;

        return 0;
    }
}

namespace Matecat\Core\Engines {

    use Exception;
    use Matecat\TestHelpers\AbstractTest;
    use Model\Engines\Structs\EngineStruct;
    use Model\Jobs\JobsMetadataMarshaller;
    use Model\Jobs\MetadataDao as JobsMetadataDao;
    use Model\Projects\MetadataDao as ProjectsMetadataDao;
    use PHPUnit\Framework\Attributes\Test;
    use Utils\Constants\EngineConstants;
    use Utils\Engines\Intento;
    use Utils\Engines\Results\MyMemory\GetMemoryResponse;
    use Utils\Registry\AppConfig;
    use Utils\Redis\RedisHandler;

    class IntentoEngineTest extends AbstractTest
    {
        private const int SETTINGS_PROJECT_ID = 990211;
        private const int SETTINGS_JOB_ID = 990212;

        private TestIntento $engine;

        protected function setUp(): void
        {
            parent::setUp();
            \Utils\Engines\IntentoCurlMock::reset();

            $struct = EngineStruct::getStruct();
            $struct->class_load = 'Intento';
            $struct->name = 'Intento';
            $struct->type = EngineConstants::MT;
            $struct->base_url = Intento::INTENTO_API_URL;
            $struct->translate_relative_url = 'ai/text/translate';
            $struct->extra_parameters = ['apikey' => 'test-api-key'];

            $this->engine = new TestIntento($struct, obtainTestDatabase());
        }

        #[Test]
        public function getSuccessReturnsGetMemoryResponseWithMatch(): void
        {
            $this->engine->queueCallResponse('{"results":["Ciao mondo"]}');

            $response = $this->engine->get([
                'segment' => 'Hello world',
                'source' => 'en-US',
                'target' => 'it-IT',
            ]);

            self::assertInstanceOf(GetMemoryResponse::class, $response);
            self::assertSame(200, $response->responseStatus);
            self::assertCount(1, $response->matches);
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

            self::assertSame(200, $response->responseStatus);
            self::assertSame([], $response->matches);
            self::assertCount(0, $this->engine->capturedCalls);
        }

        // ---------------------------------------------------------------------------------
        // Custom provider / custom routing
        //
        // Both settings moved from project metadata to job metadata so the project owner can change
        // them after creation, and are read through JobSettingsResolver with the project scope as a
        // permanent fallback for projects created before that move.
        //
        // The resolver is constructed inline from $this->database, so there is nothing to inject:
        // these seed real metadata rows and drive the real read path, the same way the MMT and Lara
        // engine suites do for the same reason.
        // ---------------------------------------------------------------------------------------

        /**
         * Intento::get() posts the parameters as a raw array (it does not ask call() to JSON-encode
         * them), so the captured curl options carry the structure directly.
         *
         * @return array<string, mixed>
         */
        private function capturedServiceParameters(): array
        {
            self::assertCount(1, $this->engine->capturedCalls);

            $postFields = $this->engine->capturedCalls[0]['options'][CURLOPT_POSTFIELDS];
            self::assertIsArray($postFields);

            return $postFields;
        }

        /**
         * @param array<string, string> $projectRows
         * @param array<string, string> $jobRows
         * @param array<string, mixed>  $extraConfig
         *
         * @return array<string, mixed> the parameters Intento was called with
         */
        private function translateWithSeededSettings(array $projectRows, array $jobRows = [], array $extraConfig = []): array
        {
            // JobSettingsResolver reads with an 86400s TTL, which would otherwise open a Redis
            // connection; setCacheTTL() is a no-op under this flag so the reads stay pure PDO.
            $previousSkipCache = AppConfig::$SKIP_SQL_CACHE;
            AppConfig::$SKIP_SQL_CACHE = true;

            $projectMetadataDao = new ProjectsMetadataDao(obtainTestDatabase());
            $jobMetadataDao = new JobsMetadataDao(obtainTestDatabase());
            $jobPassword = 'intpw_' . bin2hex(random_bytes(4));

            try {
                foreach ($projectRows as $key => $value) {
                    $projectMetadataDao->set(self::SETTINGS_PROJECT_ID, $key, $value);
                }
                if ($jobRows !== []) {
                    $jobMetadataDao->bulkSet(self::SETTINGS_JOB_ID, $jobPassword, $jobRows);
                }

                $this->engine->queueCallResponse('{"results":["Ciao mondo"]}');
                $this->engine->get(array_merge([
                    'segment' => 'Hello world',
                    'source' => 'en-US',
                    'target' => 'it-IT',
                    'pid' => self::SETTINGS_PROJECT_ID,
                ], $extraConfig, $jobRows !== [] ? ['job_password' => $jobPassword] : []));

                return $this->capturedServiceParameters();
            } finally {
                foreach (array_keys($projectRows) as $key) {
                    $projectMetadataDao->delete(self::SETTINGS_PROJECT_ID, $key);
                }
                foreach (array_keys($jobRows) as $key) {
                    $jobMetadataDao->delete(self::SETTINGS_JOB_ID, $jobPassword, $key);
                }
                AppConfig::$SKIP_SQL_CACHE = $previousSkipCache;
            }
        }

        #[Test]
        public function getWithACustomProviderAsksIntentoForThatProviderAsynchronously(): void
        {
            $parameters = $this->translateWithSeededSettings([
                JobsMetadataMarshaller::INTENTO_PROVIDER->value => 'ai.text.translate.google.translate_api.v3',
            ]);

            self::assertSame('ai.text.translate.google.translate_api.v3', $parameters['service']['provider']);
            self::assertTrue($parameters['service']['async']);
        }

        #[Test]
        public function getWithACustomRoutingAsksForBestQualityAsynchronously(): void
        {
            $parameters = $this->translateWithSeededSettings([
                JobsMetadataMarshaller::INTENTO_ROUTING->value => 'best_price',
            ]);

            self::assertSame('best_quality', $parameters['service']['routing']);
            self::assertTrue($parameters['service']['async']);
            self::assertArrayNotHasKey('provider', $parameters['service']);
        }

        #[Test]
        public function smartRoutingIsLeftToIntentoRatherThanOverridden(): void
        {
            // Intento's own default: overriding it with best_quality would silently change the
            // routing the owner asked for, which is why the branch guards on it.
            $parameters = $this->translateWithSeededSettings([
                JobsMetadataMarshaller::INTENTO_ROUTING->value => 'smart_routing',
            ]);

            self::assertArrayNotHasKey('service', $parameters);
        }

        #[Test]
        public function aProviderWinsOverARoutingWhenBothAreSet(): void
        {
            $parameters = $this->translateWithSeededSettings([
                JobsMetadataMarshaller::INTENTO_PROVIDER->value => 'ai.text.translate.deepl.api',
                JobsMetadataMarshaller::INTENTO_ROUTING->value => 'best_price',
            ]);

            self::assertSame('ai.text.translate.deepl.api', $parameters['service']['provider']);
            self::assertArrayNotHasKey('routing', $parameters['service']);
        }

        #[Test]
        public function withNeitherSettingStoredNoServiceBlockIsSent(): void
        {
            $parameters = $this->translateWithSeededSettings([]);

            self::assertArrayNotHasKey('service', $parameters);
        }

        #[Test]
        public function aJobProviderOverridesTheProjectOne(): void
        {
            // The whole point of the move: the owner edits the setting on the job after creation and
            // the engine picks it up, while older projects keep answering from the project scope.
            $parameters = $this->translateWithSeededSettings(
                [JobsMetadataMarshaller::INTENTO_PROVIDER->value => 'ai.text.translate.project.one'],
                [JobsMetadataMarshaller::INTENTO_PROVIDER->value => 'ai.text.translate.job.one'],
                ['job_id' => self::SETTINGS_JOB_ID]
            );

            self::assertSame('ai.text.translate.job.one', $parameters['service']['provider']);
        }

        #[Test]
        public function aProjectProviderStillAnswersWhenTheJobHasNoRow(): void
        {
            $parameters = $this->translateWithSeededSettings(
                [JobsMetadataMarshaller::INTENTO_PROVIDER->value => 'ai.text.translate.project.only'],
                [JobsMetadataMarshaller::INTENTO_ROUTING->value => 'smart_routing'],
                ['job_id' => self::SETTINGS_JOB_ID]
            );

            self::assertSame('ai.text.translate.project.only', $parameters['service']['provider']);
        }

        #[Test]
        public function getMalformedPayloadReturnsErrorLikeResponse(): void
        {
            $this->engine->queueCallResponse('not a json payload');

            $response = $this->engine->get([
                'segment' => 'Hello world',
                'source' => 'en-US',
                'target' => 'it-IT',
            ]);

            self::assertGreaterThan(0, $response->responseStatus);
            self::assertNotNull($response->error);
        }

        #[Test]
        public function decodeArrayErrorFormatReturnsStatusAtLeast400(): void
        {
            $response = $this->engine->decodeForTest(
                [
                    'responseStatus' => 503,
                    'error' => [
                        'response' => json_encode([
                            'error' => [
                                'code' => 503,
                                'message' => 'Upstream unavailable',
                            ],
                        ]),
                    ],
                ],
                [
                    'context' => [
                        'text' => 'Hello world',
                    ],
                ]
            );

            self::assertGreaterThanOrEqual(400, $response->responseStatus);
            self::assertNotNull($response->error);
            self::assertSame('Upstream unavailable', $response->error?->message);
        }

        #[Test]
        public function decodeAsyncIntentoFormatPollsAndReturnsMatch(): void
        {
            $this->engine->queueCallResponse('{"id":"op-123","done":true,"response":[{"results":["Traduzione async"]}]}');

            $response = $this->engine->decodeForTest(
                '{"id":"op-123","done":false}',
                [
                    'context' => [
                        'text' => 'Hello world',
                    ],
                ],
                'translate_relative_url'
            );

            self::assertSame([2], \Utils\Engines\IntentoCurlMock::$sleepCalls);
            self::assertSame(200, $response->responseStatus);
            self::assertCount(1, $response->matches);
            self::assertSame('Traduzione async', $response->matches[0]->raw_translation);
        }

        #[Test]
        public function getRoutingListParsesResponseAndIncludesSmartRouting(): void
        {
            $this->clearRedisKey('IntentoRoutings-test-api-key');

            \Utils\Engines\IntentoCurlMock::$responsesByUrl[Intento::INTENTO_API_URL . '/routing-designer'] = json_encode([
                'data' => [
                    [
                        'rt_id' => 'rt-1',
                        'name' => 'quality_first',
                        'description' => 'Use quality first route',
                    ],
                ],
            ]);

            $list = $this->engine->getRoutingList();

            self::assertArrayHasKey('smart_routing', $list);
            self::assertArrayHasKey('quality_first', $list);
            self::assertSame('rt-1', $list['quality_first']['id']);
            self::assertSame('quality_first', $list['quality_first']['name']);
        }

        #[Test]
        public function setUpdateDeleteReturnTrue(): void
        {
            self::assertTrue($this->engine->set([]));
            self::assertTrue($this->engine->update([]));
            self::assertTrue($this->engine->delete([]));
        }

        #[Test]
        public function getRoutingListReturnsEmptyWithoutApiKey(): void
        {
            $struct = EngineStruct::getStruct();
            $struct->class_load = 'Intento';
            $struct->name = 'Intento';
            $struct->type = EngineConstants::MT;
            $struct->base_url = Intento::INTENTO_API_URL;
            $struct->translate_relative_url = 'ai/text/translate';
            $struct->extra_parameters = [];

            $engineWithoutApiKey = new TestIntento($struct, obtainTestDatabase());
            self::assertSame([], $engineWithoutApiKey->getRoutingList());
        }

        #[Test]
        public function getProviderListParsesResponse(): void
        {
            $this->clearRedisKey('IntentoProviders');

            \Utils\Engines\IntentoCurlMock::$responsesByUrl[Intento::INTENTO_API_URL . '/ai/text/translate?fields=auth&integrated=true&published=true'] = json_encode([
                [
                    'id' => 'provider-1',
                    'name' => 'Provider One',
                    'vendor' => 'Vendor A',
                    'auth' => ['token' => '***'],
                ],
            ]);

            $providers = Intento::getProviderList();

            self::assertArrayHasKey('provider-1', $providers);
            self::assertSame('Provider One', $providers['provider-1']['name']);
            self::assertSame('Vendor A', $providers['provider-1']['vendor']);
            self::assertIsString($providers['provider-1']['auth_example']);
        }

        #[Test]
        public function getConfigurationParametersIncludesExpectedFlags(): void
        {
            self::assertSame(
                ['enable_mt_analysis', 'intento_routing', 'intento_provider'],
                Intento::getConfigurationParameters()
            );
        }

        private function clearRedisKey(string $key): void
        {
            try {
                (new RedisHandler())->getConnection()->del($key);
            } catch (Exception) {
                self::markTestSkipped('Redis not available for Intento routing/provider cache path');
            }
        }
    }

    class TestIntento extends Intento
    {
        /** @var list<array{url:string,options:array<int,mixed>}> */
        public array $capturedCalls = [];

        /** @var list<string|bool|null> */
        private array $queuedResponses = [];

        public function queueCallResponse(string|bool|null $response): void
        {
            $this->queuedResponses[] = $response;
        }

        /**
         * @param array<int, mixed> $curl_options
         */
        public function _call(string $url, array $curl_options = []): string|bool|null
        {
            $this->capturedCalls[] = ['url' => $url, 'options' => $curl_options];

            return array_shift($this->queuedResponses);
        }

        /**
         * @param mixed $rawValue
         * @param array<string, mixed> $parameters
         */
        public function decodeForTest(mixed $rawValue, array $parameters = [], ?string $function = null): GetMemoryResponse
        {
            return $this->_decode($rawValue, $parameters, $function);
        }
    }
}
