<?php

namespace Matecat\Core\View\API\V2\Json;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\CoversClass;
use Utils\TmKeyManagement\ClientTmKeyStruct;
use View\API\V2\Json\JobClientKeys;

#[CoversClass(JobClientKeys::class)]
class JobClientKeysTest extends AbstractTest
{
    private function makeKeyStruct(string $key = 'abc123'): ClientTmKeyStruct
    {
        $struct       = new ClientTmKeyStruct();
        $struct->key  = $key;
        $struct->r    = true;
        $struct->w    = false;
        $struct->name = 'My Key';

        return $struct;
    }

    public function testRenderEmptyArray(): void
    {
        $view   = new JobClientKeys([]);
        $result = $view->render();

        $this->assertSame([], $result);
    }

    public function testRenderItemReturnsExpectedKeys(): void
    {
        $struct = $this->makeKeyStruct('key1');
        $result = JobClientKeys::renderItem($struct);

        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('r', $result);
        $this->assertArrayHasKey('w', $result);
        $this->assertArrayHasKey('name', $result);
    }

    public function testRenderItemValues(): void
    {
        $struct = $this->makeKeyStruct('mykey');
        $result = JobClientKeys::renderItem($struct);

        $this->assertSame('mykey', $result['key']);
        $this->assertTrue($result['r']);
        $this->assertFalse($result['w']);
        $this->assertSame('My Key', $result['name']);
    }

    public function testRenderReturnsList(): void
    {
        $k1   = $this->makeKeyStruct('key1');
        $k2   = $this->makeKeyStruct('key2');
        $view = new JobClientKeys([$k1, $k2]);

        $result = $view->render();

        $this->assertCount(2, $result);
        $this->assertSame('key1', $result[0]['key']);
        $this->assertSame('key2', $result[1]['key']);
    }
}
