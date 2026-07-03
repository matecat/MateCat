<?php

namespace Matecat\Core\Utils\Autopropagation;

use Matecat\TestHelpers\AbstractTest;
use Model\Propagation\PropagationTotalStruct;
use Model\Translations\SegmentTranslationStruct;
use PHPUnit\Framework\Attributes\Test;
use Utils\Autopropagation\PropagationAnalyser;

class PropagationAnalyserTest extends AbstractTest
{
    private function makeSegment(array $overrides = []): SegmentTranslationStruct
    {
        return new SegmentTranslationStruct(array_merge([
            'id_segment' => 1,
            'id_job' => 1,
            'segment_hash' => 'abc123',
            'match_type' => 'REPETITIONS',
            'locked' => 0,
            'translation' => 'hello',
        ], $overrides));
    }

    #[Test]
    public function analyseNonIceParentPropagatesToNonIce(): void
    {
        $analyser = new PropagationAnalyser();
        $parent = $this->makeSegment(['match_type' => 'REPETITIONS', 'locked' => 0]);
        $children = [
            $this->makeSegment(['id_segment' => 2, 'match_type' => 'REPETITIONS', 'locked' => 0]),
            $this->makeSegment(['id_segment' => 3, 'match_type' => 'REPETITIONS', 'locked' => 0]),
        ];

        $result = $analyser->analyse($parent, $children);

        $this->assertInstanceOf(PropagationTotalStruct::class, $result);
        $this->assertCount(2, $result->getPropagatedIds());
        $this->assertSame(2, $analyser->getPropagatedCount());
    }

    #[Test]
    public function analyseNonIceParentDoesNotPropagateToIce(): void
    {
        $analyser = new PropagationAnalyser();
        $parent = $this->makeSegment(['match_type' => 'REPETITIONS', 'locked' => 0]);
        $iceChild = $this->makeSegment(['id_segment' => 2, 'match_type' => 'ICE', 'locked' => 1]);

        $result = $analyser->analyse($parent, [$iceChild]);

        $this->assertEmpty($result->getPropagatedIds());
        $this->assertSame(1, $analyser->getNotPropagatedIceCount());
    }

    #[Test]
    public function analyseIceParentPropagatesToMatchingIce(): void
    {
        $analyser = new PropagationAnalyser();
        $parent = $this->makeSegment(['match_type' => 'ICE', 'locked' => 1, 'segment_hash' => 'hash1']);
        $matchingIce = $this->makeSegment(['id_segment' => 2, 'match_type' => 'ICE', 'locked' => 1, 'segment_hash' => 'hash1']);

        $result = $analyser->analyse($parent, [$matchingIce]);

        $this->assertCount(1, $result->getPropagatedIds());
        $this->assertSame(1, $analyser->getPropagatedIceCount());
    }

    #[Test]
    public function analyseIceParentDoesNotPropagateToNonMatchingIce(): void
    {
        $analyser = new PropagationAnalyser();
        $parent = $this->makeSegment(['match_type' => 'ICE', 'locked' => 1, 'segment_hash' => 'hash1']);
        $nonMatching = $this->makeSegment(['id_segment' => 2, 'match_type' => 'REPETITIONS', 'locked' => 0, 'segment_hash' => 'hash2']);

        $result = $analyser->analyse($parent, [$nonMatching]);

        $this->assertEmpty($result->getPropagatedIds());
        $this->assertSame(1, $analyser->getNotPropagatedCount());
    }

    #[Test]
    public function analyseTracksVersionUpdatesOnDifferentTranslation(): void
    {
        $analyser = new PropagationAnalyser();
        $parent = $this->makeSegment(['translation' => 'new translation']);
        $child = $this->makeSegment(['id_segment' => 2, 'translation' => 'old translation']);

        $result = $analyser->analyse($parent, [$child]);

        $this->assertNotEmpty($result->getPropagatedIdsToUpdateVersion());
    }
}
