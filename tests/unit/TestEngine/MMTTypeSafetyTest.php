<?php

namespace unit\TestEngine;

use Model\Engines\Structs\EngineStruct;
use Model\TmKeyManagement\MemoryKeyStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use ReflectionMethod;
use Utils\Constants\EngineConstants;
use Utils\Engines\MMT;
use Utils\Engines\MMT\MMTServiceApi;
use Utils\TmKeyManagement\TmKeyStruct;

/**
 * Unit tests for MMT and MMTServiceApi type-safety fixes.
 * These tests verify behavior changes from PHPStan compliance work.
 *
 * Does NOT require database — all engine construction uses in-memory EngineStruct.
 */
class MMTTypeSafetyTest extends AbstractTest
{
    private function createEngineStruct(): EngineStruct
    {
        return new EngineStruct([
            'id'                     => 999,
            'name'                   => 'Test MMT',
            'type'                   => EngineConstants::MT,
            'class_load'             => 'MMT',
            'base_url'               => 'https://api.modernmt.com',
            'translate_relative_url' => 'translate',
            'extra_parameters'       => '{"MMT-License":"test-license","MMT-context-analyzer":false}',
            'others'                 => '{}',
        ]);
    }

    private function createMMT(): MMT
    {
        return new MMT($this->createEngineStruct());
    }

    // ──────────────────────────────────────────────
    // getG2FallbackSecretKey() — static, no DB needed
    // ──────────────────────────────────────────────

    #[Test]
    public function getG2FallbackSecretKeyReturnsNullableString(): void
    {
        $result = MMT::getG2FallbackSecretKey();

        $this->assertTrue($result === null || is_string($result));
    }

    #[Test]
    public function getG2FallbackSecretKeyReturnsString(): void
    {
        // Return type should be ?string (nullable string)
        $result = MMT::getG2FallbackSecretKey();

        // Result is either null or a string, never array or other type
        $this->assertTrue($result === null || is_string($result));
    }

    // ──────────────────────────────────────────────
    // _reMapKeyList() — protected, test via reflection
    // ──────────────────────────────────────────────

    #[Test]
    public function reMapKeyListPrefixesKeysCorrectly(): void
    {
        $mmt    = $this->createMMT();
        $method = new ReflectionMethod(MMT::class, '_reMapKeyList');

        $result = $method->invoke($mmt, ['abc', 'def', 'ghi']);

        $this->assertSame(['x_mm-abc', 'x_mm-def', 'x_mm-ghi'], $result);
    }

    #[Test]
    public function reMapKeyListHandlesEmptyArray(): void
    {
        $mmt    = $this->createMMT();
        $method = new ReflectionMethod(MMT::class, '_reMapKeyList');

        $result = $method->invoke($mmt, []);

        $this->assertSame([], $result);
    }

    // ──────────────────────────────────────────────
    // _reMapKeyStructsList() — protected, test via reflection
    // ──────────────────────────────────────────────

    #[Test]
    public function reMapKeyStructsListExtractsAndPrefixesKeys(): void
    {
        $mmt    = $this->createMMT();
        $method = new ReflectionMethod(MMT::class, '_reMapKeyStructsList');

        $keyStruct1          = new MemoryKeyStruct();
        $keyStruct1->tm_key  = new TmKeyStruct(['key' => 'key-1']);
        $keyStruct2          = new MemoryKeyStruct();
        $keyStruct2->tm_key  = new TmKeyStruct(['key' => 'key-2']);

        $result = $method->invoke($mmt, [$keyStruct1, $keyStruct2]);

        $this->assertSame(['x_mm-key-1', 'x_mm-key-2'], $result);
    }

    // ──────────────────────────────────────────────
    // deleteMemory() — should return array even when client returns null
    // ──────────────────────────────────────────────

    #[Test]
    public function deleteMemoryReturnsEmptyArrayWhenClientReturnsNull(): void
    {
        $mmtClient = $this->createStub(MMTServiceApi::class);
        $mmtClient->method('deleteMemory')->willReturn(null);

        $mmt = $this->getMockBuilder(MMT::class)
            ->setConstructorArgs([$this->createEngineStruct()])
            ->onlyMethods(['_getClient'])
            ->getMock();

        $mmt->expects($this->once())->method('_getClient')->willReturn($mmtClient);

        // The declared return type is array, so null from client
        // should be coalesced to empty array
        $result = $mmt->deleteMemory(['id' => 'test-memory-id']);

        $this->assertIsArray($result);
    }

    // ──────────────────────────────────────────────
    // importMemory() — void method should not return null
    // ──────────────────────────────────────────────

    #[Test]
    public function importMemoryReturnsVoidNotNull(): void
    {
        $reflection = new ReflectionMethod(MMT::class, 'importMemory');
        $this->assertSame('void', (string)$reflection->getReturnType());
    }

    // ──────────────────────────────────────────────
    // MMTServiceApi::newInstance() — returns self, not static
    // ──────────────────────────────────────────────

    #[Test]
    public function mmtServiceApiNewInstanceReturnsSelf(): void
    {
        $instance = MMTServiceApi::newInstance('https://example.com');

        $this->assertInstanceOf(MMTServiceApi::class, $instance);
    }

    // ──────────────────────────────────────────────
    // configureContribution() — should never return null
    // ──────────────────────────────────────────────

    #[Test]
    public function configureContributionAlwaysReturnsArray(): void
    {
        $mmt    = $this->createMMT();
        $method = new ReflectionMethod(MMT::class, 'configureContribution');

        // Pass a minimal config array
        $config = [
            'source'  => 'en',
            'target'  => 'it',
            'segment' => 'Hello',
            'keys'    => ['k1'],
        ];

        $result = $method->invoke($mmt, $config);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('secret_key', $result);
        $this->assertArrayHasKey('keys', $result);
        $this->assertArrayHasKey('priority', $result);
    }
}
