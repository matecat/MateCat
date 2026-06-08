<?php

namespace Matecat\Core\View\API\V2\Json;

use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\CoversClass;
use stdClass;
use View\API\V2\Json\CreationStatus;

#[CoversClass(CreationStatus::class)]
class CreationStatusTest extends AbstractTest
{
    private function makeData(array $props = []): stdClass
    {
        $data             = new stdClass();
        $data->id_project = $props['id_project'] ?? 42;

        foreach ($props as $key => $value) {
            $data->$key = $value;
        }

        return $data;
    }

    public function testRenderReturnsExpectedKeys(): void
    {
        $data   = $this->makeData();
        $view   = new CreationStatus($data);
        $result = $view->render();

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('id_project', $result);
        $this->assertArrayHasKey('project_pass', $result);
        $this->assertArrayHasKey('project_name', $result);
        $this->assertArrayHasKey('new_keys', $result);
        $this->assertArrayHasKey('analyze_url', $result);
    }

    public function testRenderStatus200(): void
    {
        $view   = new CreationStatus($this->makeData());
        $result = $view->render();

        $this->assertSame(200, $result['status']);
        $this->assertSame('Project created', $result['message']);
    }

    public function testRenderIdProjectCastToInt(): void
    {
        $view   = new CreationStatus($this->makeData(['id_project' => '99']));
        $result = $view->render();

        $this->assertSame(99, $result['id_project']);
    }

    public function testRenderNullsWhenOptionalPropertiesMissing(): void
    {
        $data   = $this->makeData();
        $view   = new CreationStatus($data);
        $result = $view->render();

        $this->assertNull($result['project_pass']);
        $this->assertNull($result['project_name']);
        $this->assertNull($result['new_keys']);
        $this->assertNull($result['analyze_url']);
    }

    public function testRenderPopulatesOptionalProperties(): void
    {
        $data = $this->makeData([
            'id_project'   => 10,
            'ppassword'    => 'secret',
            'project_name' => 'My Project',
            'new_keys'     => ['key1'],
            'analyze_url'  => 'http://example.com/analyze',
        ]);
        $view   = new CreationStatus($data);
        $result = $view->render();

        $this->assertSame('secret', $result['project_pass']);
        $this->assertSame('My Project', $result['project_name']);
        $this->assertSame(['key1'], $result['new_keys']);
        $this->assertSame('http://example.com/analyze', $result['analyze_url']);
    }
}
