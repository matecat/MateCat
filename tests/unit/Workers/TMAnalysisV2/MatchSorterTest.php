<?php

namespace unit\Workers\TMAnalysisV2;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Utils\AsyncTasks\Workers\Service\MatchSorter;

class MatchSorterTest extends TestCase
{
    private MatchSorter $sorter;

    protected function setUp(): void
    {
        $this->sorter = new MatchSorter();
    }

    #[Test]
    public function isMtMatch_returns_true_when_created_by_contains_MT(): void
    {
        $this->assertTrue($this->sorter->isMtMatch(['created_by' => 'MT']));
        $this->assertTrue($this->sorter->isMtMatch(['created_by' => 'mt']));
        $this->assertTrue($this->sorter->isMtMatch(['created_by' => 'MyMTEngine']));
    }

    #[Test]
    public function isMtMatch_returns_false_for_non_mt(): void
    {
        $this->assertFalse($this->sorter->isMtMatch(['created_by' => 'DeepL']));
        $this->assertFalse($this->sorter->isMtMatch(['created_by' => '']));
        $this->assertFalse($this->sorter->isMtMatch([]));
    }

    #[Test]
    public function sortMatches_orders_by_score_descending(): void
    {
        $tm85 = ['match' => '85%', 'ICE' => false, 'created_by' => 'TM'];
        $tm95 = ['match' => '95%', 'ICE' => false, 'created_by' => 'TM'];
        $tm75 = ['match' => '75%', 'ICE' => false, 'created_by' => 'TM'];

        $result = $this->sorter->sortMatches([], [$tm85, $tm95, $tm75]);

        $this->assertSame('95%', $result[0]['match']);
        $this->assertSame('85%', $result[1]['match']);
        $this->assertSame('75%', $result[2]['match']);
    }

    #[Test]
    public function sortMatches_places_ice_before_non_ice_at_equal_score(): void
    {
        $tmNonIce = ['match' => '100%', 'ICE' => false, 'created_by' => 'TM'];
        $tmIce    = ['match' => '100%', 'ICE' => true,  'created_by' => 'TM'];

        $result = $this->sorter->sortMatches([], [$tmNonIce, $tmIce]);

        $this->assertTrue((bool)$result[0]['ICE']);
        $this->assertFalse((bool)$result[1]['ICE']);
    }

    #[Test]
    public function sortMatches_places_mt_before_tm_at_equal_score(): void
    {
        $tmMatch = ['match' => '85%', 'ICE' => false, 'created_by' => 'TM'];
        $mtMatch = ['match' => '85%', 'ICE' => false, 'created_by' => 'MT'];

        $result = $this->sorter->sortMatches([], [$tmMatch, $mtMatch]);

        $this->assertSame('MT', $result[0]['created_by']);
        $this->assertSame('TM', $result[1]['created_by']);
    }

    #[Test]
    public function sortMatches_ice_beats_mt_at_equal_score(): void
    {
        $mtMatch  = ['match' => '100%', 'ICE' => false, 'created_by' => 'MT'];
        $iceMatch = ['match' => '100%', 'ICE' => true,  'created_by' => 'TM'];

        $result = $this->sorter->sortMatches([], [$mtMatch, $iceMatch]);

        $this->assertTrue((bool)$result[0]['ICE']);
        $this->assertSame('MT', $result[1]['created_by']);
    }

    #[Test]
    public function sortMatches_appends_non_empty_mt_result(): void
    {
        $mtResult = ['match' => '90%', 'ICE' => false, 'created_by' => 'MT'];
        $tmMatch  = ['match' => '80%', 'ICE' => false, 'created_by' => 'TM'];

        $result = $this->sorter->sortMatches($mtResult, [$tmMatch]);

        $this->assertCount(2, $result);
        $this->assertSame('90%', $result[0]['match']);
        $this->assertSame('80%', $result[1]['match']);
    }

    #[Test]
    public function sortMatches_does_not_append_empty_mt_result(): void
    {
        $tmMatch = ['match' => '80%', 'ICE' => false, 'created_by' => 'TM'];

        $result = $this->sorter->sortMatches([], [$tmMatch]);

        $this->assertCount(1, $result);
    }

    #[Test]
    public function sortMatches_returns_empty_array_when_both_inputs_empty(): void
    {
        $this->assertSame([], $this->sorter->sortMatches([], []));
    }

    #[Test]
    public function sortMatches_full_priority_chain(): void
    {
        $ice    = ['match' => '100%', 'ICE' => true,  'created_by' => 'TM'];
        $tm100  = ['match' => '100%', 'ICE' => false, 'created_by' => 'TM'];
        $mt85   = ['match' => '85%',  'ICE' => false, 'created_by' => 'MT'];
        $tm75   = ['match' => '75%',  'ICE' => false, 'created_by' => 'TM'];

        $result = $this->sorter->sortMatches($mt85, [$tm100, $ice, $tm75]);

        $this->assertCount(4, $result);
        $this->assertTrue((bool)$result[0]['ICE']);
        $this->assertSame('100%', $result[1]['match']);
        $this->assertSame('TM', $result[1]['created_by']);
        $this->assertSame('MT', $result[2]['created_by']);
        $this->assertSame('75%', $result[3]['match']);
    }

    #[Test]
    public function sortMatches_equal_scores_no_ice_no_mt_returns_zero(): void
    {
        $a = ['match' => '80%', 'ICE' => false, 'created_by' => 'TM1'];
        $b = ['match' => '80%', 'ICE' => false, 'created_by' => 'TM2'];

        $result = $this->sorter->sortMatches([], [$a, $b]);

        $this->assertCount(2, $result);
        $this->assertSame('80%', $result[0]['match']);
        $this->assertSame('80%', $result[1]['match']);
    }
}
