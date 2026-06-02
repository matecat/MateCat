<?php

namespace unit\Model\Propagation;

use Model\Propagation\PropagationTotalStruct;
use Model\Translations\SegmentTranslationStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

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
        $struct->setTotals(['total' => 100, 'countSeg' => 5, 'status' => 'DONE']);

        $totals = $struct->getTotals();
        $this->assertSame(100, $totals['total']);
        $this->assertSame(5, $totals['countSeg']);
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
