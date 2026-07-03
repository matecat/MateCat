<?php

namespace Matecat\Core\View\API\V2\Json;

use Matecat\TestHelpers\AbstractTest;
use Model\TmKeyManagement\MemoryKeyStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use Utils\TmKeyManagement\TmKeyStruct;
use View\API\V2\Json\MemoryKeys;

#[CoversClass(MemoryKeys::class)]
class MemoryKeysTest extends AbstractTest
{
    private function makePrivateKey(string $key = 'abc123', string $name = 'My Key'): MemoryKeyStruct
    {
        $tmKey            = new TmKeyStruct();
        $tmKey->key       = $key;
        $tmKey->name      = $name;
        $tmKey->is_shared = false;

        $struct         = new MemoryKeyStruct();
        $struct->uid    = 1;
        $struct->tm_key = $tmKey;

        return $struct;
    }

    private function makeSharedKey(string $key = 'shared999', string $name = 'Shared Key'): MemoryKeyStruct
    {
        $tmKey            = new TmKeyStruct();
        $tmKey->key       = $key;
        $tmKey->name      = $name;
        $tmKey->is_shared = true;

        $struct         = new MemoryKeyStruct();
        $struct->uid    = 2;
        $struct->tm_key = $tmKey;

        return $struct;
    }

    private function makeNullTmKey(): MemoryKeyStruct
    {
        $struct         = new MemoryKeyStruct();
        $struct->uid    = 3;
        $struct->tm_key = null;

        return $struct;
    }

    public function testConstructorAcceptsEmptyArray(): void
    {
        $view = new MemoryKeys([]);
        $this->assertInstanceOf(MemoryKeys::class, $view);
    }

    public function testRenderEmptyReturnsEmptyArray(): void
    {
        $view   = new MemoryKeys([]);
        $result = $view->render();

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testRenderItemReturnsKeyAndName(): void
    {
        $struct = $this->makePrivateKey('mykey', 'My Name');
        $result = MemoryKeys::renderItem($struct);

        $this->assertSame('mykey', $result['key']);
        $this->assertSame('My Name', $result['name']);
    }

    public function testRenderPrivateKeyGoesToPrivateKeys(): void
    {
        $struct = $this->makePrivateKey();
        $view   = new MemoryKeys([$struct]);
        $result = $view->render();

        $this->assertArrayHasKey('private_keys', $result);
        $this->assertCount(1, $result['private_keys']);
        $this->assertSame('abc123', $result['private_keys'][0]['key']);
    }

    public function testRenderSharedKeyGoesToSharedKeys(): void
    {
        $struct = $this->makeSharedKey();
        $view   = new MemoryKeys([$struct]);
        $result = $view->render();

        $this->assertArrayHasKey('shared_keys', $result);
        $this->assertCount(1, $result['shared_keys']);
        $this->assertSame('shared999', $result['shared_keys'][0]['key']);
    }

    public function testRenderMixedKeysGroupedCorrectly(): void
    {
        $private = $this->makePrivateKey('p1', 'Private');
        $shared  = $this->makeSharedKey('s1', 'Shared');
        $view    = new MemoryKeys([$private, $shared]);
        $result  = $view->render();

        $this->assertArrayHasKey('private_keys', $result);
        $this->assertArrayHasKey('shared_keys', $result);
        $this->assertCount(1, $result['private_keys']);
        $this->assertCount(1, $result['shared_keys']);
    }

    public function testRenderItemWithNullTmKeyReturnsNulls(): void
    {
        $struct = $this->makeNullTmKey();
        $result = MemoryKeys::renderItem($struct);

        $this->assertNull($result['key']);
        $this->assertNull($result['name']);
    }

    public function testRenderWithNullTmKeyDefaultsToPrivateKeys(): void
    {
        $struct = $this->makeNullTmKey();
        $view   = new MemoryKeys([$struct]);
        $result = $view->render();

        $this->assertArrayHasKey('private_keys', $result);
        $this->assertCount(1, $result['private_keys']);
    }
}
