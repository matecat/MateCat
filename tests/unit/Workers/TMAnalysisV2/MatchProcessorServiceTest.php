<?php

namespace Tests\Unit\Workers\TMAnalysisV2;

use Model\Analysis\Constants\InternalMatchesConstants;
use Model\FeaturesBase\FeatureSet;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\MatchProcessorService;
use Utils\AsyncTasks\Workers\Service\MatchSorter;
use Utils\Constants\Ices;
use Utils\Constants\TranslationStatus;


class MatchProcessorServiceTest extends TestCase
{
    private MatchProcessorService $service;

    protected function setUp(): void
    {
        $this->service = new MatchProcessorService(new MatchSorter());
    }

    #[Test]
    public function isMtMatch_returns_true_when_created_by_equals_MT(): void
    {
        $this->assertTrue($this->service->isMtMatch(['created_by' => 'MT']));
    }

    #[Test]
    public function isMtMatch_is_case_insensitive(): void
    {
        $this->assertTrue($this->service->isMtMatch(['created_by' => 'mt']));
        $this->assertTrue($this->service->isMtMatch(['created_by' => 'Mt']));
        $this->assertTrue($this->service->isMtMatch(['created_by' => 'mT']));
    }

    #[Test]
    public function isMtMatch_returns_true_when_created_by_contains_MT_as_substring(): void
    {
        $this->assertTrue($this->service->isMtMatch(['created_by' => 'MyMTEngine']));
        $this->assertTrue($this->service->isMtMatch(['created_by' => 'GoogMT']));
    }

    #[Test]
    public function isMtMatch_returns_false_for_non_mt_created_by(): void
    {
        $this->assertFalse($this->service->isMtMatch(['created_by' => 'DeepL']));
        $this->assertFalse($this->service->isMtMatch(['created_by' => 'MyTM']));
        $this->assertFalse($this->service->isMtMatch(['created_by' => 'Reverso']));
    }

    #[Test]
    public function isMtMatch_returns_false_when_created_by_is_empty_string(): void
    {
        $this->assertFalse($this->service->isMtMatch(['created_by' => '']));
    }

    #[Test]
    public function isMtMatch_returns_false_when_created_by_key_is_missing(): void
    {
        $this->assertFalse($this->service->isMtMatch([]));
    }

    #[Test]
    public function sortMatches_orders_by_score_descending(): void
    {
        $tm85 = ['match' => '85%', 'ICE' => false, 'created_by' => 'TM'];
        $tm95 = ['match' => '95%', 'ICE' => false, 'created_by' => 'TM'];
        $tm75 = ['match' => '75%', 'ICE' => false, 'created_by' => 'TM'];

        $result = $this->service->sortMatches([], [$tm85, $tm95, $tm75]);

        $this->assertSame('95%', $result[0]['match']);
        $this->assertSame('85%', $result[1]['match']);
        $this->assertSame('75%', $result[2]['match']);
    }

    #[Test]
    public function sortMatches_places_ice_before_non_ice_at_equal_score(): void
    {
        $tmNonIce = ['match' => '100%', 'ICE' => false, 'created_by' => 'TM'];
        $tmIce    = ['match' => '100%', 'ICE' => true,  'created_by' => 'TM'];

        $result = $this->service->sortMatches([], [$tmNonIce, $tmIce]);

        $this->assertTrue((bool)$result[0]['ICE'], 'ICE match must be first at equal score');
        $this->assertFalse((bool)$result[1]['ICE']);
    }

    #[Test]
    public function sortMatches_places_mt_before_tm_at_equal_score(): void
    {
        $tmMatch = ['match' => '85%', 'ICE' => false, 'created_by' => 'TM'];
        $mtMatch = ['match' => '85%', 'ICE' => false, 'created_by' => 'MT'];

        $result = $this->service->sortMatches([], [$tmMatch, $mtMatch]);

        $this->assertSame('MT', $result[0]['created_by'], 'MT must precede TM at equal score');
        $this->assertSame('TM', $result[1]['created_by']);
    }

    #[Test]
    public function sortMatches_appends_non_empty_mt_result_before_sorting(): void
    {
        $mtResult = ['match' => '90%', 'ICE' => false, 'created_by' => 'MT'];
        $tmMatch  = ['match' => '80%', 'ICE' => false, 'created_by' => 'TM'];

        $result = $this->service->sortMatches($mtResult, [$tmMatch]);

        $this->assertCount(2, $result);
        $this->assertSame('90%', $result[0]['match']);
        $this->assertSame('80%', $result[1]['match']);
    }

    #[Test]
    public function sortMatches_does_not_append_empty_mt_result(): void
    {
        $tmMatch = ['match' => '80%', 'ICE' => false, 'created_by' => 'TM'];

        $result = $this->service->sortMatches([], [$tmMatch]);

        $this->assertCount(1, $result);
        $this->assertSame('80%', $result[0]['match']);
    }

    #[Test]
    public function sortMatches_returns_empty_array_when_both_inputs_are_empty(): void
    {
        $result = $this->service->sortMatches([], []);

        $this->assertSame([], $result);
    }

    #[Test]
    public function sortMatches_mixed_ice_tm_mt_produces_correct_order(): void
    {
        $ice    = ['match' => '100%', 'ICE' => true,  'created_by' => 'TM'];
        $tm100  = ['match' => '100%', 'ICE' => false, 'created_by' => 'TM'];
        $mt85   = ['match' => '85%',  'ICE' => false, 'created_by' => 'MT'];
        $tm75   = ['match' => '75%',  'ICE' => false, 'created_by' => 'TM'];

        $result = $this->service->sortMatches($mt85, [$tm100, $ice, $tm75]);

        $this->assertCount(4, $result);
        $this->assertTrue((bool)$result[0]['ICE']);
        $this->assertSame('100%', $result[0]['match']);
        $this->assertFalse((bool)$result[1]['ICE']);
        $this->assertSame('100%', $result[1]['match']);
        $this->assertSame('TM', $result[1]['created_by']);
        $this->assertSame('85%', $result[2]['match']);
        $this->assertSame('MT', $result[2]['created_by']);
        $this->assertSame('75%', $result[3]['match']);
        $this->assertSame('TM', $result[3]['created_by']);
    }

    #[Test]
    public function calculateWordDiscount_returns_zero_eq_and_std_for_ice_at_zero_rate(): void
    {
        $payableRates = [
            InternalMatchesConstants::TM_ICE => 0,
            InternalMatchesConstants::MT     => 75,
            InternalMatchesConstants::NO_MATCH => 100,
        ];

        [$matchType, $eqWordCount, $stdWordCount] = $this->service->calculateWordDiscount(
            InternalMatchesConstants::TM_ICE,
            100.0,
            $payableRates
        );

        $this->assertSame(InternalMatchesConstants::TM_ICE, $matchType);
        $this->assertEqualsWithDelta(0.0, $eqWordCount, 0.001);
        $this->assertEqualsWithDelta(0.0, $stdWordCount, 0.001);
    }

    #[Test]
    public function calculateWordDiscount_for_mt_eq_uses_mt_rate_and_std_uses_no_match_rate(): void
    {
        $payableRates = [
            InternalMatchesConstants::MT       => 75,
            InternalMatchesConstants::NO_MATCH => 100,
        ];

        [$matchType, $eqWordCount, $stdWordCount] = $this->service->calculateWordDiscount(
            InternalMatchesConstants::MT,
            100.0,
            $payableRates
        );

        $this->assertSame(InternalMatchesConstants::MT, $matchType);
        $this->assertEqualsWithDelta(75.0, $eqWordCount, 0.001);
        $this->assertEqualsWithDelta(100.0, $stdWordCount, 0.001);
    }

    #[Test]
    public function calculateWordDiscount_for_ice_mt_std_uses_no_match_rate(): void
    {
        $payableRates = [
            InternalMatchesConstants::ICE_MT   => 50,
            InternalMatchesConstants::NO_MATCH => 100,
        ];

        [$matchType, $eqWordCount, $stdWordCount] = $this->service->calculateWordDiscount(
            InternalMatchesConstants::ICE_MT,
            10.0,
            $payableRates
        );

        $this->assertSame(InternalMatchesConstants::ICE_MT, $matchType);
        $this->assertEqualsWithDelta(5.0, $eqWordCount, 0.001);
        $this->assertEqualsWithDelta(10.0, $stdWordCount, 0.001);
    }

    #[Test]
    public function calculateWordDiscount_for_top_quality_mt_std_uses_no_match_rate(): void
    {
        $payableRates = [
            InternalMatchesConstants::TOP_QUALITY_MT => 85,
            InternalMatchesConstants::NO_MATCH       => 100,
        ];

        [$matchType, $eqWordCount, $stdWordCount] = $this->service->calculateWordDiscount(
            InternalMatchesConstants::TOP_QUALITY_MT,
            200.0,
            $payableRates
        );

        $this->assertSame(InternalMatchesConstants::TOP_QUALITY_MT, $matchType);
        $this->assertEqualsWithDelta(170.0, $eqWordCount, 0.001);
        $this->assertEqualsWithDelta(200.0, $stdWordCount, 0.001);
    }

    #[Test]
    public function calculateWordDiscount_for_higher_quality_mt_std_uses_no_match_rate(): void
    {
        $payableRates = [
            InternalMatchesConstants::HIGHER_QUALITY_MT => 90,
            InternalMatchesConstants::NO_MATCH          => 100,
        ];

        [$matchType, $eqWordCount, $stdWordCount] = $this->service->calculateWordDiscount(
            InternalMatchesConstants::HIGHER_QUALITY_MT,
            50.0,
            $payableRates
        );

        $this->assertSame(InternalMatchesConstants::HIGHER_QUALITY_MT, $matchType);
        $this->assertEqualsWithDelta(45.0, $eqWordCount, 0.001);
        $this->assertEqualsWithDelta(50.0, $stdWordCount, 0.001);
    }

    #[Test]
    public function calculateWordDiscount_for_standard_quality_mt_std_uses_no_match_rate(): void
    {
        $payableRates = [
            InternalMatchesConstants::STANDARD_QUALITY_MT => 60,
            InternalMatchesConstants::NO_MATCH            => 100,
        ];

        [$matchType, $eqWordCount, $stdWordCount] = $this->service->calculateWordDiscount(
            InternalMatchesConstants::STANDARD_QUALITY_MT,
            40.0,
            $payableRates
        );

        $this->assertSame(InternalMatchesConstants::STANDARD_QUALITY_MT, $matchType);
        $this->assertEqualsWithDelta(24.0, $eqWordCount, 0.001);
        $this->assertEqualsWithDelta(40.0, $stdWordCount, 0.001);
    }

    #[Test]
    public function calculateWordDiscount_for_tm_85_94_eq_equals_std(): void
    {
        $payableRates = [
            InternalMatchesConstants::TM_85_94 => 60,
            InternalMatchesConstants::NO_MATCH => 100,
        ];

        [$matchType, $eqWordCount, $stdWordCount] = $this->service->calculateWordDiscount(
            InternalMatchesConstants::TM_85_94,
            100.0,
            $payableRates
        );

        $this->assertSame(InternalMatchesConstants::TM_85_94, $matchType);
        $this->assertEqualsWithDelta(60.0, $eqWordCount, 0.001);
        $this->assertEqualsWithDelta(60.0, $stdWordCount, 0.001);
    }

    #[Test]
    public function calculateWordDiscount_for_tm_100_eq_equals_std(): void
    {
        $payableRates = [
            InternalMatchesConstants::TM_100   => 30,
            InternalMatchesConstants::NO_MATCH => 100,
        ];

        [$matchType, $eqWordCount, $stdWordCount] = $this->service->calculateWordDiscount(
            InternalMatchesConstants::TM_100,
            200.0,
            $payableRates
        );

        $this->assertSame(InternalMatchesConstants::TM_100, $matchType);
        $this->assertEqualsWithDelta(60.0, $eqWordCount, 0.001);
        $this->assertEqualsWithDelta(60.0, $stdWordCount, 0.001);
    }

    #[Test]
    public function calculateWordDiscount_defaults_rate_to_100_when_match_type_not_in_payable_rates(): void
    {
        [$matchType, $eqWordCount, $stdWordCount] = $this->service->calculateWordDiscount(
            'UNKNOWN_TYPE',
            10.0,
            []
        );

        $this->assertSame('UNKNOWN_TYPE', $matchType);
        $this->assertEqualsWithDelta(10.0, $eqWordCount, 0.001);
        $this->assertEqualsWithDelta(10.0, $stdWordCount, 0.001);
    }

    #[Test]
    public function calculateWordDiscount_for_no_match_eq_equals_std(): void
    {
        $payableRates = [
            InternalMatchesConstants::NO_MATCH => 100,
        ];

        [$matchType, $eqWordCount, $stdWordCount] = $this->service->calculateWordDiscount(
            InternalMatchesConstants::NO_MATCH,
            25.0,
            $payableRates
        );

        $this->assertSame(InternalMatchesConstants::NO_MATCH, $matchType);
        $this->assertEqualsWithDelta(25.0, $eqWordCount, 0.001);
        $this->assertEqualsWithDelta(25.0, $stdWordCount, 0.001);
    }

    #[Test]
    public function determinePreTranslateStatus_sets_approved_and_locked_for_ice_100_match(): void
    {
        $tmData = [
            'suggestion_match' => InternalMatchesConstants::TM_100,
            'match_type'       => InternalMatchesConstants::TM_ICE,
            'status'           => TranslationStatus::STATUS_NEW,
            'locked'           => false,
        ];

        $params                         = new \stdClass();
        $params->target                 = 'en-US';
        $params->pretranslate_100       = false;
        $params->mt_qe_workflow_enabled = false;

        Ices::$iceLockDisabledForTargetLangs = [];

        $result = $this->service->determinePreTranslateStatus($tmData, $params);

        $this->assertSame(TranslationStatus::STATUS_APPROVED, $result['status']);
        $this->assertTrue($result['locked']);
    }

    #[Test]
    public function determinePreTranslateStatus_no_ice_lock_when_target_language_is_disabled(): void
    {
        $tmData = [
            'suggestion_match' => InternalMatchesConstants::TM_100,
            'match_type'       => InternalMatchesConstants::TM_ICE,
            'status'           => TranslationStatus::STATUS_NEW,
            'locked'           => false,
        ];

        $params                         = new \stdClass();
        $params->target                 = 'zh-CN';
        $params->pretranslate_100       = false;
        $params->mt_qe_workflow_enabled = false;

        Ices::$iceLockDisabledForTargetLangs = ['zh'];

        $result = $this->service->determinePreTranslateStatus($tmData, $params);

        $this->assertSame(TranslationStatus::STATUS_NEW, $result['status']);
        $this->assertFalse($result['locked']);

        Ices::$iceLockDisabledForTargetLangs = [];
    }

    #[Test]
    public function determinePreTranslateStatus_sets_translated_unlocked_when_pretranslate_100_is_true_and_match_type_is_not_ice(): void
    {
        $tmData = [
            'suggestion_match' => InternalMatchesConstants::TM_100,
            'match_type'       => InternalMatchesConstants::TM_100,
            'status'           => TranslationStatus::STATUS_NEW,
            'locked'           => false,
        ];

        $params                         = new \stdClass();
        $params->target                 = 'en-US';
        $params->pretranslate_100       = true;
        $params->mt_qe_workflow_enabled = false;

        $result = $this->service->determinePreTranslateStatus($tmData, $params);

        $this->assertSame(TranslationStatus::STATUS_TRANSLATED, $result['status']);
        $this->assertFalse($result['locked']);
    }

    #[Test]
    public function determinePreTranslateStatus_no_change_when_pretranslate_100_is_false_and_match_type_is_not_ice(): void
    {
        $tmData = [
            'suggestion_match' => InternalMatchesConstants::TM_100,
            'match_type'       => InternalMatchesConstants::TM_100,
            'status'           => TranslationStatus::STATUS_DRAFT,
            'locked'           => false,
        ];

        $params                         = new \stdClass();
        $params->target                 = 'en-US';
        $params->pretranslate_100       = false;
        $params->mt_qe_workflow_enabled = false;

        $result = $this->service->determinePreTranslateStatus($tmData, $params);

        $this->assertSame(TranslationStatus::STATUS_DRAFT, $result['status']);
        $this->assertFalse($result['locked']);
    }

    #[Test]
    public function determinePreTranslateStatus_no_change_when_suggestion_match_does_not_contain_100(): void
    {
        $tmData = [
            'suggestion_match' => '85%',
            'match_type'       => InternalMatchesConstants::TM_ICE,
            'status'           => TranslationStatus::STATUS_DRAFT,
            'locked'           => false,
        ];

        $params                         = new \stdClass();
        $params->target                 = 'en-US';
        $params->pretranslate_100       = true;
        $params->mt_qe_workflow_enabled = false;

        Ices::$iceLockDisabledForTargetLangs = [];

        $result = $this->service->determinePreTranslateStatus($tmData, $params);

        $this->assertSame(TranslationStatus::STATUS_DRAFT, $result['status']);
        $this->assertFalse($result['locked']);
    }

    #[Test]
    public function determinePreTranslateStatus_sets_approved_unlocked_when_match_type_is_ice_mt_and_mt_qe_is_enabled(): void
    {
        $tmData = [
            'suggestion_match' => '85%',
            'match_type'       => InternalMatchesConstants::ICE_MT,
            'status'           => TranslationStatus::STATUS_NEW,
            'locked'           => true,
        ];

        $params                         = new \stdClass();
        $params->target                 = 'en-US';
        $params->pretranslate_100       = false;
        $params->mt_qe_workflow_enabled = true;

        $result = $this->service->determinePreTranslateStatus($tmData, $params);

        $this->assertSame(TranslationStatus::STATUS_APPROVED, $result['status']);
        $this->assertFalse($result['locked']);
    }

    #[Test]
    public function determinePreTranslateStatus_no_change_when_match_type_is_ice_mt_but_mt_qe_is_disabled(): void
    {
        $tmData = [
            'suggestion_match' => '85%',
            'match_type'       => InternalMatchesConstants::ICE_MT,
            'status'           => TranslationStatus::STATUS_NEW,
            'locked'           => false,
        ];

        $params                         = new \stdClass();
        $params->target                 = 'en-US';
        $params->pretranslate_100       = false;
        $params->mt_qe_workflow_enabled = false;

        $result = $this->service->determinePreTranslateStatus($tmData, $params);

        $this->assertSame(TranslationStatus::STATUS_NEW, $result['status']);
        $this->assertFalse($result['locked']);
    }

    #[Test]
    public function determinePreTranslateStatus_mt_qe_overwrites_pretranslate_block_for_ice_mt_100_match(): void
    {
        $tmData = [
            'suggestion_match' => InternalMatchesConstants::TM_100,
            'match_type'       => InternalMatchesConstants::ICE_MT,
            'status'           => TranslationStatus::STATUS_NEW,
            'locked'           => false,
        ];

        $params                         = new \stdClass();
        $params->target                 = 'en-US';
        $params->pretranslate_100       = true;
        $params->mt_qe_workflow_enabled = true;

        Ices::$iceLockDisabledForTargetLangs = [];

        $result = $this->service->determinePreTranslateStatus($tmData, $params);

        $this->assertSame(TranslationStatus::STATUS_APPROVED, $result['status']);
        $this->assertFalse($result['locked']);
    }

    #[Test]
    public function determinePreTranslateStatus_returns_full_tmData_array_unchanged_when_no_condition_matches(): void
    {
        $tmData = [
            'suggestion_match' => '75%',
            'match_type'       => InternalMatchesConstants::TM_75_84,
            'status'           => TranslationStatus::STATUS_DRAFT,
            'locked'           => false,
            'extra_field'      => 'preserved',
        ];

        $params                         = new \stdClass();
        $params->target                 = 'en-US';
        $params->pretranslate_100       = false;
        $params->mt_qe_workflow_enabled = false;

        $result = $this->service->determinePreTranslateStatus($tmData, $params);

        $this->assertSame(TranslationStatus::STATUS_DRAFT, $result['status']);
        $this->assertFalse($result['locked']);
        $this->assertSame('preserved', $result['extra_field']);
    }

    // ── postProcessMatch tests ──────────────────────────────────────────

    #[Test]
    public function postProcessMatch_for_tm_match_returns_suggestion_warning_and_errors(): void
    {
        $segment = 'Hello <g id="1">world</g>';
        $match = [
            'segment'     => 'Hello <g id="1">world</g>',
            'translation' => 'Ciao <g id="1">mondo</g>',
            'created_by'  => 'TM-User',
        ];

        $featureSet = new FeatureSet();

        $result = $this->service->postProcessMatch($segment, 'en-US', 'it-IT', $match, $featureSet, InternalMatchesConstants::TM_100, false, 1);

        $this->assertArrayHasKey('suggestion', $result);
        $this->assertArrayHasKey('warning', $result);
        $this->assertArrayHasKey('serialized_errors_list', $result);
        $this->assertIsString($result['suggestion']);
    }

    #[Test]
    public function postProcessMatch_for_mt_match_runs_realign_and_returns_result(): void
    {
        $segment = 'Hello world';
        $match = [
            'segment'     => 'Hello world',
            'translation' => 'Ciao mondo',
            'created_by'  => 'MT!',
        ];

        $featureSet = new FeatureSet();

        $result = $this->service->postProcessMatch($segment, 'en-US', 'it-IT', $match, $featureSet, InternalMatchesConstants::MT, false, 1);

        $this->assertArrayHasKey('suggestion', $result);
        $this->assertArrayHasKey('warning', $result);
        $this->assertArrayHasKey('serialized_errors_list', $result);
        $this->assertIsString($result['suggestion']);
    }

    #[Test]
    public function postProcessMatch_for_plain_text_tm_match_returns_no_warning(): void
    {
        $segment = 'Simple text without tags';
        $match = [
            'segment'     => 'Simple text without tags',
            'translation' => 'Testo semplice senza tag',
            'created_by'  => 'TM-User',
        ];

        $featureSet = new FeatureSet();

        $result = $this->service->postProcessMatch($segment, 'en-US', 'it-IT', $match, $featureSet, InternalMatchesConstants::TM_100, false, 1);

        $this->assertSame(0, $result['warning']);
        $this->assertSame('', $result['serialized_errors_list']);
    }

    #[Test]
    public function postProcessMatch_detects_tag_mismatch_and_sets_warning(): void
    {
        $segment = 'Hello <g id="1">world</g>';
        $match = [
            'segment'     => 'Hello <g id="1">world</g>',
            'translation' => 'Ciao mondo',  // missing tag
            'created_by'  => 'TM-User',
        ];

        $featureSet = new FeatureSet();

        $result = $this->service->postProcessMatch($segment, 'en-US', 'it-IT', $match, $featureSet, InternalMatchesConstants::TM_100, false, 1);

        $this->assertSame(1, $result['warning']);
        $this->assertNotEmpty($result['serialized_errors_list']);
    }
}
