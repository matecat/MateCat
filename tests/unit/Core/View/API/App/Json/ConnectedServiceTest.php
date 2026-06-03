<?php

namespace Matecat\Core\View\API\App\Json;

use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\ConnectedServiceStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use View\API\App\Json\ConnectedService;

#[CoversClass(ConnectedService::class)]
class ConnectedServiceTest extends AbstractTest
{
    private function makeStruct(int $id = 1): ConnectedServiceStruct
    {
        $struct                    = new ConnectedServiceStruct();
        $struct->id                = $id;
        $struct->uid               = 42;
        $struct->service           = 'google';
        $struct->email             = 'test@example.com';
        $struct->name              = 'Test User';
        $struct->oauth_access_token = null;
        $struct->created_at        = '2024-01-01 00:00:00';
        $struct->updated_at        = null;
        $struct->disabled_at       = null;
        $struct->expired_at        = null;
        $struct->is_default        = 1;

        return $struct;
    }

    public function testConstructorAcceptsEmptyArray(): void
    {
        $view = new ConnectedService([]);
        $this->assertInstanceOf(ConnectedService::class, $view);
    }

    public function testRenderEmptyDataReturnsEmptyArray(): void
    {
        $view   = new ConnectedService([]);
        $result = $view->render();

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testRenderItemReturnsExpectedKeys(): void
    {
        $struct = $this->makeStruct(5);
        $view   = new ConnectedService([$struct]);
        $result = $view->renderItem($struct);

        $this->assertSame(5, $result['id']);
        $this->assertSame(42, $result['uid']);
        $this->assertSame('google', $result['service']);
        $this->assertSame('test@example.com', $result['email']);
        $this->assertSame('Test User', $result['name']);
        $this->assertArrayHasKey('oauth_access_token', $result);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('updated_at', $result);
        $this->assertArrayHasKey('disabled_at', $result);
        $this->assertArrayHasKey('expired_at', $result);
        $this->assertIsBool($result['is_default']);
        $this->assertTrue($result['is_default']);
    }

    public function testRenderReturnsOneItemPerStruct(): void
    {
        $s1   = $this->makeStruct(1);
        $s2   = $this->makeStruct(2);
        $view = new ConnectedService([$s1, $s2]);

        $result = $view->render();

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame(2, $result[1]['id']);
    }

    public function testRenderItemCastsIdToInt(): void
    {
        $struct     = $this->makeStruct();
        $struct->id = '7';
        $view       = new ConnectedService([$struct]);
        $result     = $view->renderItem($struct);

        $this->assertSame(7, $result['id']);
    }

    public function testIsDefaultFalseWhenZero(): void
    {
        $struct             = $this->makeStruct();
        $struct->is_default = 0;
        $view               = new ConnectedService([$struct]);
        $result             = $view->renderItem($struct);

        $this->assertFalse($result['is_default']);
    }
}
