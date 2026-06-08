<?php

namespace Matecat\Core\View\API\V2\Json;

use Matecat\TestHelpers\AbstractTest;
use Model\Propagation\PropagationTotalStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use View\API\V2\Json\Propagation;

#[CoversClass(Propagation::class)]
class PropagationTest extends AbstractTest
{
    private function makeStruct(
        array $totals = [],
        array $propagatedIds = []
    ): PropagationTotalStruct {
        $struct = new PropagationTotalStruct();

        if (!empty($totals)) {
            $struct->setTotals($totals);
        }

        foreach ($propagatedIds as $id) {
            $struct->addPropagatedId($id);
        }

        return $struct;
    }

    public function testRenderReturnsExpectedKeys(): void
    {
        $struct = $this->makeStruct();
        $view   = new Propagation($struct);
        $result = $view->render();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('totals', $result);
        $this->assertArrayHasKey('propagated_ids', $result);
        $this->assertArrayHasKey('segments_for_propagation', $result);
    }

    public function testRenderTotalsReflectsSetValues(): void
    {
        $struct = $this->makeStruct(['total' => 5, 'countSeg' => 3, 'status' => 'translated']);
        $view   = new Propagation($struct);
        $result = $view->render();

        $this->assertSame(5, $result['totals']['total']);
        $this->assertSame(3, $result['totals']['countSeg']);
        $this->assertSame('translated', $result['totals']['status']);
    }

    public function testRenderPropagatedIdsReflectsAddedIds(): void
    {
        $struct = $this->makeStruct([], ['101', '202']);
        $view   = new Propagation($struct);
        $result = $view->render();

        $this->assertContains('101', $result['propagated_ids']);
        $this->assertContains('202', $result['propagated_ids']);
    }

    public function testRenderSegmentsForPropagationHasExpectedStructure(): void
    {
        $struct = $this->makeStruct();
        $view   = new Propagation($struct);
        $result = $view->render();

        $this->assertIsArray($result['segments_for_propagation']);
        $this->assertArrayHasKey('propagated', $result['segments_for_propagation']);
        $this->assertArrayHasKey('not_propagated', $result['segments_for_propagation']);
    }

    public function testRenderEmptyStructReturnsEmptyCollections(): void
    {
        $struct = new PropagationTotalStruct();
        $view   = new Propagation($struct);
        $result = $view->render();

        $this->assertSame([], $result['totals']);
        $this->assertSame([], $result['propagated_ids']);
    }
}
