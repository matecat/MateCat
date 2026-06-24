<?php

namespace Matecat\Core\View\API\V2\Json;

use Matecat\TestHelpers\AbstractTest;
use Model\Engines\Structs\EngineStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use View\API\V2\Json\Engine;

#[CoversClass(Engine::class)]
class EngineTest extends AbstractTest
{
    private function makeEngineStruct(int $id = 1, string $name = 'MyMemory'): EngineStruct
    {
        $struct       = new EngineStruct();
        $struct->id   = $id;
        $struct->name = $name;
        $struct->type = 'MT';

        return $struct;
    }

    public function testConstructorAcceptsEmptyArray(): void
    {
        $view = new Engine();
        $this->assertInstanceOf(Engine::class, $view);
    }

    public function testConstructorAcceptsEngineStructArray(): void
    {
        $view = new Engine([$this->makeEngineStruct()]);
        $this->assertInstanceOf(Engine::class, $view);
    }

    public function testRenderWithNoArgsUsesConstructorData(): void
    {
        $struct = $this->makeEngineStruct(1, 'MyMemory');
        $view   = new Engine([$struct]);
        $result = $view->render();

        $this->assertCount(1, $result);
        $this->assertIsArray($result[0]);
    }

    public function testRenderWithExplicitDataOverridesConstructorData(): void
    {
        $struct1 = $this->makeEngineStruct(1, 'MyMemory');
        $struct2 = $this->makeEngineStruct(2, 'DeepL');

        $view   = new Engine([$struct1]);
        $result = $view->render([$struct2]);

        $this->assertCount(1, $result);
    }

    public function testRenderReturnsArrayRepresentationForEachEngine(): void
    {
        $struct = $this->makeEngineStruct(5, 'MMT');
        $view   = new Engine([$struct]);
        $result = $view->render();

        $this->assertArrayHasKey('id', $result[0]);
        $this->assertSame(5, $result[0]['id']);
        $this->assertSame('MMT', $result[0]['name']);
    }

    public function testRenderWithMultipleEngines(): void
    {
        $s1 = $this->makeEngineStruct(1, 'MyMemory');
        $s2 = $this->makeEngineStruct(2, 'DeepL');
        $s3 = $this->makeEngineStruct(3, 'Google');

        $view   = new Engine([$s1, $s2, $s3]);
        $result = $view->render();

        $this->assertCount(3, $result);
    }

    public function testRenderWithEmptyDataAndEmptyConstructorReturnsEmptyArray(): void
    {
        $view   = new Engine();
        $result = $view->render();

        $this->assertSame([], $result);
    }
}
