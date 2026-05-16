<?php

declare(strict_types=1);

namespace unit\Engines;

use CURLFile;
use DomainException;
use Model\Engines\Structs\EngineStruct;
use Model\TmKeyManagement\MemoryKeyStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use stdClass;
use TestHelpers\AbstractTest;
use Utils\Engines\NONE;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;

/**
 * Testable subclass that exposes protected AbstractEngine methods
 * without altering any behavior.
 */
class TestableNONE extends NONE
{
    public function exposedFixLangCode(string $lang): string
    {
        return $this->_fixLangCode($lang);
    }

    public function exposedGetCurlFile(string $file): CURLFile
    {
        return $this->getCurlFile($file);
    }

    public function exposedGoogleTranslateFallback(array $_config): GetMemoryResponse
    {
        return $this->GoogleTranslateFallback($_config);
    }

    public function setContentType(string $type): void
    {
        $this->content_type = $type;
    }

    public function setLogging(bool $logging): void
    {
        $this->logging = $logging;
    }
}

class AbstractEngineTest extends AbstractTest
{
    private TestableNONE $engine;

    protected function setUp(): void
    {
        $struct            = EngineStruct::getStruct();
        $struct->class_load = 'NONE';
        $struct->name       = 'TestEngine';
        $struct->penalty    = 14;
        $struct->others     = ['custom_key' => 'custom_value'];
        $struct->extra_parameters = ['param_key' => 'param_value'];

        $this->engine = new TestableNONE($struct);
    }

    // ---------------------------------------------------------------
    // validateConfigurationParams
    // ---------------------------------------------------------------

    #[Test]
    public function validateConfigurationParamsReturnsTrueWhenAllKeysKnown(): void
    {
        // NONE::getConfigurationParameters() returns [] —
        // an empty stdClass has no keys to reject.
        $extra = new stdClass();
        self::assertTrue($this->engine->validateConfigurationParams($extra));
    }

    #[Test]
    public function validateConfigurationParamsReturnsFalseForUnknownKey(): void
    {
        $extra              = new stdClass();
        $extra->unknownKey  = 'value';
        self::assertFalse($this->engine->validateConfigurationParams($extra));
    }

    // ---------------------------------------------------------------
    // Stub / default-implementation methods
    // ---------------------------------------------------------------

    #[Test]
    public function memoryExistsReturnsNull(): void
    {
        $key = new MemoryKeyStruct();
        self::assertNull($this->engine->memoryExists($key));
    }

    #[Test]
    public function deleteMemoryReturnsEmptyArray(): void
    {
        self::assertSame([], $this->engine->deleteMemory([]));
    }

    #[Test]
    public function getMemoryIfMineReturnsNull(): void
    {
        $key = new MemoryKeyStruct();
        self::assertNull($this->engine->getMemoryIfMine($key));
    }

    #[Test]
    public function getQualityEstimationReturnsNull(): void
    {
        self::assertNull(
            $this->engine->getQualityEstimation('en', 'it', 'hello', 'ciao')
        );
    }

    #[Test]
    public function importMemoryIsNoOp(): void
    {
        $user = new UserStruct();
        $this->engine->importMemory('/fake/path.tmx', 'key123', $user);
        // No exception, no side effect — just verifies the default body runs.
        self::assertTrue(true);
    }

    #[Test]
    public function syncMemoriesIsNoOp(): void
    {
        $this->engine->syncMemories(['id' => 1]);
        self::assertTrue(true);
    }

    // ---------------------------------------------------------------
    // _call() — happy path via file:// URL
    // ---------------------------------------------------------------

    #[Test]
    public function callRawWithFileUrlReturnsFileContents(): void
    {
        $tmp = tempnam('/tmp', 'ae_test_');
        file_put_contents($tmp, '{"status":"ok"}');

        try {
            $result = $this->engine->_call('file://' . $tmp);
            self::assertIsString($result);
            self::assertStringContainsString('ok', (string)$result);
        } finally {
            @unlink($tmp);
        }
    }

    #[Test]
    public function callRawWithJsonContentTypeCoversJsonLogBranch(): void
    {
        $tmp = tempnam('/tmp', 'ae_test_');
        file_put_contents($tmp, '{"key":"value"}');

        $this->engine->setContentType('json');

        try {
            $result = $this->engine->_call('file://' . $tmp);
            self::assertIsString($result);
            self::assertStringContainsString('value', (string)$result);
        } finally {
            @unlink($tmp);
        }
    }

    #[Test]
    public function callRawWithLoggingDisabledSkipsLogBlock(): void
    {
        $tmp = tempnam('/tmp', 'ae_test_');
        file_put_contents($tmp, 'raw-text');

        $this->engine->setLogging(false);

        try {
            $result = $this->engine->_call('file://' . $tmp);
            self::assertSame('raw-text', $result);
        } finally {
            @unlink($tmp);
        }
    }

    // ---------------------------------------------------------------
    // _call() — error path (connection refused)
    // ---------------------------------------------------------------

    #[Test]
    public function callRawWithUnreachableUrlReturnsErrorJson(): void
    {
        $result = $this->engine->_call('http://localhost:1/fail', [
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT        => 1,
        ]);

        self::assertIsString($result);
        $decoded = json_decode((string)$result, true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('error', $decoded);
        self::assertLessThan(0, $decoded['error']['code']);
    }

    // ---------------------------------------------------------------
    // GoogleTranslateFallback — exercises the protected fallback path
    // ---------------------------------------------------------------

    #[Test]
    public function googleTranslateFallbackReturnsGetMemoryResponse(): void
    {
        $result = $this->engine->exposedGoogleTranslateFallback([
            'source'     => 'en',
            'target'     => 'it',
            'segment'    => 'test segment',
            'secret_key' => 'invalid-key',
        ]);

        self::assertInstanceOf(GetMemoryResponse::class, $result);
    }
}
