<?php

namespace Matecat\Core\View\API\V2\Json;

use Matecat\TestHelpers\AbstractTest;
use Model\Propagation\PropagationResult;
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

    /**
     * `render()` hands back a `PropagationResult` rather than a loose array. The view is the only
     * producer of that object in the request path, so this is what makes the type reach the
     * interface and both controllers.
     */
    public function testRenderReturnsAPropagationResult(): void
    {
        $result = (new Propagation($this->makeStruct()))->render();

        $this->assertInstanceOf(PropagationResult::class, $result);
    }

    public function testRenderTotalsReflectsSetValues(): void
    {
        $struct = $this->makeStruct(['total' => 5, 'repetitions_count' => 3, 'status' => 'translated']);
        $result = (new Propagation($struct))->render();

        $this->assertSame(5, $result->totals['total']);
        $this->assertSame(3, $result->totals['repetitions_count']);
        $this->assertSame('translated', $result->totals['status']);
    }

    public function testRenderPropagatedIdsReflectsAddedIds(): void
    {
        $result = (new Propagation($this->makeStruct([], ['101', '202'])))->render();

        $this->assertSame(['101', '202'], $result->propagatedIds);
    }

    public function testRenderSegmentsForPropagationHasExpectedStructure(): void
    {
        $result = (new Propagation($this->makeStruct()))->render();

        $this->assertArrayHasKey('propagated', $result->segmentsForPropagation);
        $this->assertArrayHasKey('not_propagated', $result->segmentsForPropagation);
    }

    public function testRenderEmptyStructReturnsEmptyCollections(): void
    {
        $result = (new Propagation(new PropagationTotalStruct()))->render();

        $this->assertSame([], $result->totals);
        $this->assertSame([], $result->propagatedIds);
    }

    /**
     * The response body must not change shape. `PropagationResult` is `JsonSerializable`, and the
     * editor reads `propagation.propagated_ids` and `propagation.segments_for_propagation`
     * (`public/js/setTranslationUtil.js:263,274`), so the three legacy snake_case keys have to
     * survive serialisation of whatever `render()` now returns.
     */
    public function testRenderSerialisesToTheLegacyWireKeys(): void
    {
        $result = (new Propagation($this->makeStruct([], ['101'])))->render();

        $decoded = json_decode(json_encode($result), true);

        $this->assertSame(['totals', 'propagated_ids', 'segments_for_propagation'], array_keys($decoded));
        $this->assertSame(['101'], $decoded['propagated_ids']);
    }
}
