<?php

namespace Matecat\Core\View\API\V2\Json;

use Matecat\TestHelpers\AbstractTest;
use Model\Users\MetadataStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use View\API\V2\Json\UserMetadata;

#[CoversClass(UserMetadata::class)]
class UserMetadataTest extends AbstractTest
{
    private function makeMetadata(string $key, string $value): MetadataStruct
    {
        $m        = new MetadataStruct();
        $m->key   = $key;
        $m->value = $value;

        return $m;
    }

    public function testRenderNullReturnsEmpty(): void
    {
        $result = UserMetadata::renderMetadataCollection(null);

        $this->assertSame([], $result);
    }

    public function testRenderEmptyArrayReturnsEmpty(): void
    {
        $result = UserMetadata::renderMetadataCollection([]);

        $this->assertSame([], $result);
    }

    public function testRenderReturnableKeyIncluded(): void
    {
        $meta   = $this->makeMetadata('gplus_picture', 'http://example.com/pic.jpg');
        $result = UserMetadata::renderMetadataCollection([$meta]);

        $this->assertArrayHasKey('gplus_picture', $result);
        $this->assertSame('http://example.com/pic.jpg', $result['gplus_picture']);
    }

    public function testRenderNonReturnableKeyExcluded(): void
    {
        $meta   = $this->makeMetadata('secret_key', 'secret_value');
        $result = UserMetadata::renderMetadataCollection([$meta]);

        $this->assertArrayNotHasKey('secret_key', $result);
    }

    public function testRenderMixedKeysOnlyReturnsAllowed(): void
    {
        $m1     = $this->makeMetadata('gplus_picture', 'http://pic.example.com');
        $m2     = $this->makeMetadata('internal_data', 'hidden');
        $result = UserMetadata::renderMetadataCollection([$m1, $m2]);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('gplus_picture', $result);
    }
}
