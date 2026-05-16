<?php

declare(strict_types=1);

namespace unit\Engines;

use Exception;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Constants\EngineConstants;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\Engines\SmartMATE;

class SmartMATEEngineTest extends AbstractTest
{
    private TestSmartMATE $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $struct = EngineStruct::getStruct();
        $struct->id = 1;
        $struct->class_load = SmartMATE::class;
        $struct->name = 'SmartMATE';
        $struct->type = EngineConstants::MT;
        $struct->base_url = 'https://api.smartmate.test/translate/api/v2.1';
        $struct->translate_relative_url = 'translate';
        $struct->others = [
            'oauth_url' => 'https://api.smartmate.test/oauth/token',
        ];
        $struct->extra_parameters = [
            'token' => null,
            'token_endlife' => 0,
            'client_id' => 'cid-test',
            'client_secret' => 'secret-test',
        ];

        $this->engine = new TestSmartMATE($struct);
    }

    #[Test]
    public function getSuccessWithOauthTokenAndTranslationCallReturnsMatch(): void
    {
        $this->engine->queueCallResponse((string)json_encode([
            'access_token' => 'token-1',
            'expires_in' => 3600,
        ]));
        $this->engine->queueCallResponse((string)json_encode([
            'translation' => 'Ciao mondo',
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
        self::assertGreaterThanOrEqual(1, $this->engine->oauthAuthenticateCalls);
    }

    #[Test]
    public function getTokenExpiredAndAuthFailureRetriesThenSucceeds(): void
    {
        $this->engine->queueCallResponse((string)json_encode([
            'access_token' => 'token-first',
            'expires_in' => 3600,
        ]));
        $this->engine->queueCallResponse((string)json_encode([
            'access_token' => 'token-second',
            'expires_in' => 3600,
        ]));
        $this->engine->queueCallResponse((string)json_encode([
            'translation' => 'Traduzione dopo refresh',
        ]));
        $this->engine->setForceAuthFailureOnce(true);

        $response = $this->engine->get([
            'segment' => 'Retry me',
            'source' => 'en-US',
            'target' => 'it-IT',
        ]);

        self::assertSame(200, $response->responseStatus);
        self::assertCount(1, $response->matches);
        self::assertSame('Traduzione dopo refresh', $response->matches[0]->raw_translation);
        self::assertGreaterThanOrEqual(2, $this->engine->oauthAuthenticateCalls);
    }

    #[Test]
    public function getWithSkipAnalysisReturnsEmptyResponseStatus200(): void
    {
        $this->engine->setTokenEndLifeForTest(time() + 600);

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
    }

    #[Test]
    public function getErrorReturnsResponseStatusGreaterThanOrEqualTo400(): void
    {
        $this->engine->setTokenEndLifeForTest(time() + 600);
        $this->engine->setForceGenericGetError(true);

        $response = $this->engine->get([
            'segment' => 'Hello world',
            'source' => 'en-US',
            'target' => 'it-IT',
        ]);

        self::assertGreaterThanOrEqual(400, $response->responseStatus);
        self::assertSame(503, $response->responseStatus);
        self::assertSame('service unavailable', $response->error?->message);
    }

    #[Test]
    public function decodeSmartMATESpecificJsonFormatReturnsMatch(): void
    {
        $response = $this->engine->decodeForTest((string)json_encode([
            'translation' => 'Salut tout le monde',
        ]), ['text' => 'Hello world']);

        self::assertSame(200, $response->responseStatus);
        self::assertCount(1, $response->matches);
        self::assertSame('Hello world', $response->matches[0]->raw_segment);
        self::assertSame('Salut tout le monde', $response->matches[0]->raw_translation);
    }

    #[Test]
    public function oauthTraitAuthParametersArePostFormEncoded(): void
    {
        $parameters = $this->engine->authParametersForTest();

        self::assertTrue((bool)($parameters[CURLOPT_POST] ?? false));
        self::assertArrayHasKey(CURLOPT_POSTFIELDS, $parameters);
        self::assertStringContainsString('grant_type=client_credentials', (string)$parameters[CURLOPT_POSTFIELDS]);
        self::assertStringContainsString('scope=translate', (string)$parameters[CURLOPT_POSTFIELDS]);
    }

    #[Test]
    public function oauthFailurePathReturnsErrorResponseWithoutRealHttp(): void
    {
        $this->engine->setSuppressAuthenticateExceptions(false);
        $this->engine->queueCallResponse((string)json_encode([
            'error' => [
                'response' => (string)json_encode([
                    'error' => 'invalid_client',
                ]),
            ],
        ]));

        $response = $this->engine->get([
            'segment' => 'Hello world',
            'source' => 'en-US',
            'target' => 'it-IT',
        ]);

        self::assertGreaterThanOrEqual(400, $response->responseStatus);
    }

    #[Test]
    public function recursionLimitReturns499ErrorResponse(): void
    {
        $this->engine->setAlwaysAuthFailure(true);

        $response = $this->engine->get([
            'segment' => 'Loop forever',
            'source' => 'en-US',
            'target' => 'it-IT',
        ]);

        self::assertSame(499, $response->responseStatus);
        self::assertSame(-499, $response->error?->code);
    }

    #[Test]
    public function engineHelpersAndConfigurationMethodsAreStable(): void
    {
        self::assertTrue($this->engine->set([]));
        self::assertTrue($this->engine->update([]));
        self::assertTrue($this->engine->delete([]));

        self::assertSame(['enable_mt_analysis'], SmartMATE::getConfigurationParameters());
        self::assertSame('en', $this->engine->fixLangCodeForTest(' EN-us '));

        $filled = $this->engine->fillCallParametersForTest([
            'segment' => 's',
            'source' => 'en-US',
            'target' => 'it-IT',
        ]);
        self::assertSame(['text' => 's', 'from' => 'en-US', 'to' => 'it-IT'], $filled);
    }

    #[Test]
    public function smartMateAuthHelpersAndChecksHandleExpectedShapes(): void
    {
        $formatted = $this->engine->formatAuthenticateErrorForTest([
            'error' => [
                'response' => (string)json_encode(['error' => 'token failed']),
            ],
        ]);

        self::assertSame('token failed', $formatted['error_description']);

        $this->engine->setResultForAuthCheck([
            'error' => [
                'message' => 'token is expired now',
                'code' => 0,
            ],
        ]);
        self::assertTrue($this->engine->checkAuthFailureForTest());

        $this->engine->setResultForAuthCheck([
            'error' => [
                'message' => 'generic',
                'code' => -10,
            ],
        ]);
        self::assertTrue($this->engine->checkAuthFailureForTest());

        $this->engine->setTokenEndLifeForTest(321);
        self::assertSame(321, $this->engine->getTokenEndLifeForTest());

        $this->engine->setTokenEndLifeForTest();
        self::assertGreaterThan(time(), $this->engine->getTokenEndLifeForTest());
    }

    #[Test]
    public function constructorThrowsWhenEngineTypeIsNotMt(): void
    {
        $struct = EngineStruct::getStruct();
        $struct->class_load = SmartMATE::class;
        $struct->name = 'SmartMATE';
        $struct->type = EngineConstants::TM;
        $struct->others = ['oauth_url' => 'https://api.smartmate.test/oauth/token'];
        $struct->extra_parameters = ['client_id' => 'id', 'client_secret' => 'secret'];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('is not a MT engine');

        new TestSmartMATE($struct);
    }
}

class TestSmartMATE extends SmartMATE
{
    /** @var list<string|bool|null> */
    private array $queuedResponses = [];

    public int $oauthAuthenticateCalls = 0;

    private bool $forceAuthFailureOnce = false;
    private bool $authFailureTriggered = false;
    private bool $alwaysAuthFailure = false;
    private bool $forceGenericGetError = false;
    private bool $suppressAuthenticateExceptions = true;

    public function queueCallResponse(string|bool|null $response): void
    {
        $this->queuedResponses[] = $response;
    }

    public function setForceAuthFailureOnce(bool $value): void
    {
        $this->forceAuthFailureOnce = $value;
    }

    public function setAlwaysAuthFailure(bool $value): void
    {
        $this->alwaysAuthFailure = $value;
    }

    public function setForceGenericGetError(bool $value): void
    {
        $this->forceGenericGetError = $value;
    }

    public function setSuppressAuthenticateExceptions(bool $value): void
    {
        $this->suppressAuthenticateExceptions = $value;
    }

    /**
     * @param array<int, mixed> $curl_options
     */
    public function _call(string $url, array $curl_options = []): string|bool|null
    {
        unset($curl_options);

        if (!empty($this->queuedResponses)) {
            $value = array_shift($this->queuedResponses);
            return $value;
        }

        if (str_contains($url, 'oauth')) {
            return (string)json_encode([
                'access_token' => 'default-token',
                'expires_in' => 3600,
            ]);
        }

        return (string)json_encode([
            'translation' => 'default-translation',
        ]);
    }

    public function call(string $function, array $parameters = [], bool $isPostRequest = false, bool $isJsonRequest = false): void
    {
        if ($this->forceGenericGetError) {
            $this->result = [
                'error' => [
                    'code' => 503,
                    'message' => 'service unavailable',
                    'response' => '',
                ],
                'responseStatus' => 503,
            ];

            return;
        }

        if (
            $this->alwaysAuthFailure ||
            ($this->forceAuthFailureOnce && !$this->authFailureTriggered)
        ) {
            $this->authFailureTriggered = true;
            $this->result = [
                'error' => [
                    'code' => -401,
                    'message' => 'token is expired',
                    'response' => '',
                ],
                'responseStatus' => 401,
            ];

            return;
        }

        parent::call($function, $parameters, $isPostRequest, $isJsonRequest);
    }

    protected function _authenticate(): void
    {
        $this->oauthAuthenticateCalls++;

        if (!$this->suppressAuthenticateExceptions) {
            parent::_authenticate();

            return;
        }

        try {
            parent::_authenticate();
        } catch (Exception) {
            if (empty((string)$this->token)) {
                $this->token = 'fallback-token';
            }

            if ($this->token_endlife <= time()) {
                $this->_setTokenEndLife(time() + 3600);
            }
        }
    }

    protected function _checkAuthFailure(): bool
    {
        if (
            !is_array($this->result) ||
            !isset($this->result['error']) ||
            !is_array($this->result['error']) ||
            !array_key_exists('message', $this->result['error']) ||
            !array_key_exists('code', $this->result['error'])
        ) {
            return false;
        }

        return parent::_checkAuthFailure();
    }

    /** @param array<string, mixed> $parameters */
    public function decodeForTest(string $rawValue, array $parameters): GetMemoryResponse
    {
        return $this->_decode($rawValue, $parameters);
    }

    /** @return array<int, mixed> */
    public function authParametersForTest(): array
    {
        return $this->getAuthParameters();
    }

    /** @param array<string, mixed> $response */
    public function formatAuthenticateErrorForTest(array $response): array
    {
        return $this->_formatAuthenticateError($response);
    }

    /** @param array<string, mixed> $result */
    public function setResultForAuthCheck(array $result): void
    {
        $this->result = $result;
    }

    public function checkAuthFailureForTest(): bool
    {
        return $this->_checkAuthFailure();
    }

    public function setTokenEndLifeForTest(?int $expiresInSeconds = null): void
    {
        $this->_setTokenEndLife($expiresInSeconds);
    }

    public function getTokenEndLifeForTest(): int
    {
        return $this->token_endlife;
    }

    public function fixLangCodeForTest(string $lang): string
    {
        return $this->_fixLangCode($lang);
    }

    /** @param array<string, mixed> $config */
    public function fillCallParametersForTest(array $config): array
    {
        return $this->_fillCallParameters($config);
    }
}
