<?php

namespace Matecat\Core\Model\TranslationsSplit;

use Matecat\TestHelpers\AbstractTest;
use Model\TranslationsSplit\SegmentSplitStruct;
use PHPUnit\Framework\Attributes\Test;

class SegmentSplitStructTest extends AbstractTest
{
    #[Test]
    public function getStructReturnsNewInstance(): void
    {
        $struct = SegmentSplitStruct::getStruct();
        $this->assertInstanceOf(SegmentSplitStruct::class, $struct);
    }

    #[Test]
    public function canSetProperties(): void
    {
        $struct = new SegmentSplitStruct();
        $struct->id_segment = 1;
        $struct->id_job = 10;
        $struct->source_chunk_lengths = [100, 200];
        $struct->target_chunk_lengths = [150, 250];

        $this->assertSame(1, $struct->id_segment);
        $this->assertSame(10, $struct->id_job);
        $this->assertSame([100, 200], $struct->source_chunk_lengths);
        $this->assertSame([150, 250], $struct->target_chunk_lengths);
    }

    #[Test]
    public function chunkLengthsCanBeString(): void
    {
        $struct = new SegmentSplitStruct();
        $struct->source_chunk_lengths = '[100,200]';
        $struct->target_chunk_lengths = '[150,250]';

        $this->assertSame('[100,200]', $struct->source_chunk_lengths);
        $this->assertSame('[150,250]', $struct->target_chunk_lengths);
    }

    #[Test]
    public function chunkLengthsDefaultToNull(): void
    {
        $struct = new SegmentSplitStruct();

        $this->assertNull($struct->source_chunk_lengths);
        $this->assertNull($struct->target_chunk_lengths);
    }
}
