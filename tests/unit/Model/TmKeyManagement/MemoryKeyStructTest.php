<?php

namespace unit\Model\TmKeyManagement;

use Model\TmKeyManagement\MemoryKeyStruct;
use TestHelpers\AbstractTest;
use Utils\TmKeyManagement\TmKeyStruct;

class MemoryKeyStructTest extends AbstractTest
{
    /**
     * Test to ensure `toArray` method returns all properties as an associative array
     * when no `TmKeyStruct` is present.
     */
    public function testToArrayWithoutTmKey(): void
    {
        $memoryKeyStruct = new MemoryKeyStruct();
        $memoryKeyStruct->uid = 123;

        $result = $memoryKeyStruct->toArray();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('uid', $result);
        $this->assertArrayHasKey('tm_key', $result);
        $this->assertNull($result['tm_key']);
        $this->assertEquals(123, $result['uid']);
    }

    /**
     * Test to ensure `toArray` method returns all properties including a nested `TmKeyStruct`.
     */
    public function testToArrayWithTmKey(): void
    {
        $tmKeyStruct = new TmKeyStruct();
        $tmKeyStruct->tm = true;
        $tmKeyStruct->name = "test key";

        $memoryKeyStruct = new MemoryKeyStruct();
        $memoryKeyStruct->uid = 456;
        $memoryKeyStruct->tm_key = $tmKeyStruct;

        $result = $memoryKeyStruct->toArray();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('uid', $result);
        $this->assertArrayHasKey('tm_key', $result);
        $this->assertEquals(456, $result['uid']);
        $this->assertIsArray($result['tm_key']);
        $this->assertTrue($result['tm_key']['tm']);
        $this->assertEquals("test key", $result['tm_key']['name']);
    }

    /**
     * Test to ensure `toArray` handles a `null` value for `tm_key` correctly.
     */
    public function testToArrayWithNullTmKey(): void
    {
        $memoryKeyStruct = new MemoryKeyStruct();
        $memoryKeyStruct->uid = 789;
        $memoryKeyStruct->tm_key = null;

        $result = $memoryKeyStruct->toArray();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('uid', $result);
        $this->assertArrayHasKey('tm_key', $result);
        $this->assertEquals(789, $result['uid']);
        $this->assertNull($result['tm_key']);
    }

    /**
     * Test to ensure `toArray` works with a partially populated `TmKeyStruct`.
     */
    public function testToArrayWithPartialTmKeyButFullStructure(): void
    {
        $tmKeyStruct = new TmKeyStruct();
        $tmKeyStruct->complete_format = true; //enable the complete format
        $tmKeyStruct->r = true;
        $tmKeyStruct->key = 'xxx';

        $memoryKeyStruct = new MemoryKeyStruct();
        $memoryKeyStruct->uid = 101112;
        $memoryKeyStruct->tm_key = $tmKeyStruct;

        $result = $memoryKeyStruct->toArray();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('uid', $result);
        $this->assertArrayHasKey('tm_key', $result);
        $this->assertIsArray($result['tm_key']);
        $this->assertEquals(1, $result['tm_key']['r']);
        $this->assertArrayHasKey('w', $result['tm_key']);
        $this->assertEquals(0, $result['tm_key']['w']);
    }
}