<?php

namespace Matecat\Core\Model\Propagation;

use Matecat\TestHelpers\AbstractTest;
use Model\Propagation\PropagationTotalStruct;
use Model\Translations\SegmentTranslationStruct;
use PHPUnit\Framework\Attributes\Test;

class PropagationTotalStructTest extends AbstractTest
{
    private function makeSegment(int $id = 1): SegmentTranslationStruct
    {
        return new SegmentTranslationStruct([
            'id_segment' => $id,
            'id_job' => 1,
            'eq_word_count' => 10,
        ]);
    }

    #[Test]
    public function setAndGetTotals(): void
    {
        $struct = new PropagationTotalStruct();
        $struct->setTotals(['total' => 100, 'repetitions_count' => 5, 'status' => 'DONE']);

        $totals = $struct->getTotals();
        $this->assertSame(100, $totals['total']);
        $this->assertSame(5, $totals['repetitions_count']);
        $this->assertSame('DONE', $totals['status']);
    }

    #[Test]
    public function addPropagatedIdDeduplicates(): void
    {
        $struct = new PropagationTotalStruct();
        $struct->addPropagatedId('1');
        $struct->addPropagatedId('1');
        $struct->addPropagatedId('2');

        $this->assertCount(2, $struct->getPropagatedIds());
    }

    #[Test]
    public function addPropagatedIdToUpdateVersionDeduplicates(): void
    {
        $struct = new PropagationTotalStruct();
        $struct->addPropagatedIdToUpdateVersion('1');
        $struct->addPropagatedIdToUpdateVersion('1');

        $this->assertCount(1, $struct->getPropagatedIdsToUpdateVersion());
    }

    #[Test]
    public function addPropagatedIce(): void
    {
        $struct = new PropagationTotalStruct();
        $seg = $this->makeSegment(10);
        $struct->addPropagatedIce($seg);

        $segments = $struct->getSegmentsForPropagation();
        $this->assertSame(10, $segments['propagated']['ice']['id'][0]);
    }

    #[Test]
    public function addNotPropagatedIce(): void
    {
        $struct = new PropagationTotalStruct();
        $seg = $this->makeSegment(20);
        $struct->addNotPropagatedIce($seg);

        $segments = $struct->getSegmentsForPropagation();
        $this->assertSame(20, $segments['not_propagated']['ice']['id'][0]);
    }

    #[Test]
    public function addPropagatedNotIce(): void
    {
        $struct = new PropagationTotalStruct();
        $seg = $this->makeSegment(30);
        $struct->addPropagatedNotIce($seg);

        $segments = $struct->getSegmentsForPropagation();
        $this->assertSame(30, $segments['propagated']['not_ice']['id'][0]);
    }

    #[Test]
    public function addNotPropagatedNotIce(): void
    {
        $struct = new PropagationTotalStruct();
        $seg = $this->makeSegment(40);
        $struct->addNotPropagatedNotIce($seg);

        $segments = $struct->getSegmentsForPropagation();
        $this->assertSame(40, $segments['not_propagated']['not_ice']['id'][0]);
    }

    #[Test]
    public function getAllToPropagateAggregatesIceAndNotIce(): void
    {
        $struct = new PropagationTotalStruct();
        $struct->addPropagatedIce($this->makeSegment(1));
        $struct->addPropagatedNotIce($this->makeSegment(2));

        $all = $struct->getAllToPropagate();
        $this->assertCount(2, $all);
    }

    /**
     * The propagated-id list is stored in exactly one place. `addPropagatedId()` used to append it
     * twice — to `$propagated_ids` and again to `$segments_for_propagation['propagated_ids']` — which
     * left two spellings of the same answer with nothing keeping them in step. Every consumer reads
     * the top-level list (`getPropagatedIds()`, `jsonSerialize()['propagated_ids']`, and the editor at
     * `public/js/setTranslationUtil.js:263`), so the nested copy is the one that goes.
     */
    #[Test]
    public function addPropagatedIdDoesNotAlsoWriteIntoSegmentsForPropagation(): void
    {
        $struct = new PropagationTotalStruct();
        $struct->addPropagatedId('1');
        $struct->addPropagatedId('2');

        $this->assertSame(['1', '2'], $struct->getPropagatedIds());
        $this->assertArrayNotHasKey('propagated_ids', $struct->getSegmentsForPropagation());
    }

    /**
     * `segments_for_propagation` describes segments split four ways, and its shape is read positionally
     * by the editor (`segments_for_propagation.not_propagated.ice.id`). Pinning the top-level keys keeps
     * an unrelated list from being smuggled back into it.
     */
    #[Test]
    public function segmentsForPropagationCarriesOnlyThePropagatedAndNotPropagatedGroups(): void
    {
        $struct = new PropagationTotalStruct();
        $struct->addPropagatedId('1');
        $struct->addPropagatedIce($this->makeSegment(10));
        $struct->addNotPropagatedNotIce($this->makeSegment(20));

        $this->assertSame(['propagated', 'not_propagated'], array_keys($struct->getSegmentsForPropagation()));
    }

    #[Test]
    public function jsonSerializeReturnsExpectedKeys(): void
    {
        $struct = new PropagationTotalStruct();
        $json = $struct->jsonSerialize();

        $this->assertArrayHasKey('totals', $json);
        $this->assertArrayHasKey('propagated_ids', $json);
        $this->assertArrayHasKey('propagated_ids_to_update_version', $json);
        $this->assertArrayHasKey('segments_for_propagation', $json);
    }
}
