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

namespace unit\Engines {

    use Exception;
    use Model\Engines\Structs\EngineStruct;
    use PHPUnit\Framework\Attributes\Test;
    use TestHelpers\AbstractTest;
    use Utils\Constants\EngineConstants;
    use Utils\Engines\Intento;
    use Utils\Engines\IntentoCurlMock;
    use Utils\Engines\Results\MyMemory\GetMemoryResponse;
    use Utils\Redis\RedisHandler;

    class IntentoEngineTest extends AbstractTest
    {
        private TestIntento $engine;

        protected function setUp(): void
        {
            parent::setUp();
            IntentoCurlMock::reset();

            $struct = EngineStruct::getStruct();
            $struct->class_load = 'Intento';
            $struct->name = 'Intento';
            $struct->type = EngineConstants::MT;
            $struct->base_url = Intento::INTENTO_API_URL;
            $struct->translate_relative_url = 'ai/text/translate';
            $struct->extra_parameters = ['apikey' => 'test-api-key'];

            $this->engine = new TestIntento($struct);
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

            self::assertSame([2], IntentoCurlMock::$sleepCalls);
            self::assertSame(200, $response->responseStatus);
            self::assertCount(1, $response->matches);
            self::assertSame('Traduzione async', $response->matches[0]->raw_translation);
        }

        #[Test]
        public function getRoutingListParsesResponseAndIncludesSmartRouting(): void
        {
            $this->clearRedisKey('IntentoRoutings-test-api-key');

            IntentoCurlMock::$responsesByUrl[Intento::INTENTO_API_URL . '/routing-designer'] = json_encode([
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

            $engineWithoutApiKey = new TestIntento($struct);
            self::assertSame([], $engineWithoutApiKey->getRoutingList());
        }

        #[Test]
        public function getProviderListParsesResponse(): void
        {
            $this->clearRedisKey('IntentoProviders');

            IntentoCurlMock::$responsesByUrl[Intento::INTENTO_API_URL . '/ai/text/translate?fields=auth&integrated=true&published=true'] = json_encode([
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
