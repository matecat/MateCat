<?php

namespace Matecat\Core\Workers\TMAnalysisV2;

use Matecat\TestHelpers\AbstractTest;
use Model\Analysis\Constants\InternalMatchesConstants;
use Model\DataAccess\Database;
use Model\FeaturesBase\FeatureSet;
use Model\MTQE\Templates\DTO\MTQEWorkflowParams;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Utils\AsyncTasks\Workers\Analysis\TMAnalysis\Service\MatchProcessorService;
use Utils\AsyncTasks\Workers\Service\MatchSorter;
use Utils\Constants\Ices;
use Utils\Constants\TranslationStatus;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;


class MatchProcessorServiceTest extends AbstractTest
{
    private MatchProcessorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MatchProcessorService(new MatchSorter(), obtainTestDatabase());
    }

    protected function tearDown(): void
    {
        Ices::$iceLockDisabledForTargetLangs = [];
        parent::tearDown();
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

        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class));

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

        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class));

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

        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class));

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

        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class));

        $result = $this->service->postProcessMatch($segment, 'en-US', 'it-IT', $match, $featureSet, InternalMatchesConstants::TM_100, false, 1);

        $this->assertSame(1, $result['warning']);
        $this->assertNotEmpty($result['serialized_errors_list']);
    }

    /**
     * Builds the analysis data as TMAnalysisWorker does: no `status` or `locked` key, so a segment
     * the method leaves untouched has neither key and the update does not write those columns.
     *
     * @return array<string, mixed>
     */
    private function preConfirmTmData(string $matchType): array
    {
        return [
            'suggestion_match' => '100%',
            'match_type'       => $matchType,
        ];
    }

    /**
     * {ICE, 100%} x {pre-confirm on, off} x {3 statuses} x {lock on, off}.
     *
     * The options of the other match class are set to the opposite values, so each case also proves
     * that a 101% option never drives a 100% match and vice versa.
     *
     * @return array<string, array{string, bool, string, bool}>
     */
    public static function preConfirmMatrixProvider(): array
    {
        $cases = [];
        foreach ([InternalMatchesConstants::TM_ICE, InternalMatchesConstants::TM_100] as $matchType) {
            foreach ([true, false] as $preConfirm) {
                foreach ([
                             TranslationStatus::STATUS_TRANSLATED,
                             TranslationStatus::STATUS_APPROVED,
                             TranslationStatus::STATUS_APPROVED2,
                         ] as $status) {
                    foreach ([true, false] as $lock) {
                        $name = sprintf(
                            '%s, pre-confirm %s, %s, lock %s',
                            $matchType,
                            $preConfirm ? 'on' : 'off',
                            $status,
                            $lock ? 'on' : 'off'
                        );
                        $cases[$name] = [$matchType, $preConfirm, $status, $lock];
                    }
                }
            }
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('preConfirmMatrixProvider')]
    public function determinePreTranslateStatus_applies_the_pre_confirm_options(string $matchType, bool $preConfirm, string $status, bool $lock): void
    {
        $isIce = $matchType === InternalMatchesConstants::TM_ICE;
        $otherStatus = $status === TranslationStatus::STATUS_APPROVED2 ? TranslationStatus::STATUS_TRANSLATED : TranslationStatus::STATUS_APPROVED2;

        $params = (object)[
            'target'                  => 'it-IT',
            'mt_qe_workflow_enabled'  => false,
            'pretranslate_101'        => $isIce ? $preConfirm : !$preConfirm,
            'pretranslate_101_status' => $isIce ? $status : $otherStatus,
            'pretranslate_101_lock'   => $isIce ? $lock : !$lock,
            'pretranslate_100'        => $isIce ? !$preConfirm : $preConfirm,
            'pretranslate_100_status' => $isIce ? $otherStatus : $status,
            'pretranslate_100_lock'   => $isIce ? !$lock : $lock,
        ];

        $result = $this->service->determinePreTranslateStatus($this->preConfirmTmData($matchType), $params);

        if ($preConfirm) {
            $this->assertSame($status, $result['status']);
            $this->assertSame($lock, $result['locked']);
        } else {
            $this->assertArrayNotHasKey('status', $result);
            $this->assertArrayNotHasKey('locked', $result);
        }
    }

    /**
     * Legacy elements: queued before the options existed (no keys at all), or built for a project
     * that predates them (keys present, all null). Expected values are the output of the method
     * before the options were introduced.
     *
     * @return array<string, array{array<string, mixed>, string, bool, ?string, ?bool}>
     */
    public static function legacyElementProvider(): array
    {
        $withoutKeys = [];
        $withNullKeys = [
            'pretranslate_101'        => null,
            'pretranslate_101_status' => null,
            'pretranslate_101_lock'   => null,
            'pretranslate_100_status' => null,
            'pretranslate_100_lock'   => null,
        ];

        return [
            'no keys, ICE'                       => [$withoutKeys, InternalMatchesConstants::TM_ICE, false, TranslationStatus::STATUS_APPROVED, true],
            'no keys, ICE, pretranslate_100 on'  => [$withoutKeys, InternalMatchesConstants::TM_ICE, true, TranslationStatus::STATUS_APPROVED, true],
            'no keys, 100% pretranslate_100 on'  => [$withoutKeys, InternalMatchesConstants::TM_100, true, TranslationStatus::STATUS_TRANSLATED, false],
            'no keys, 100% pretranslate_100 off' => [$withoutKeys, InternalMatchesConstants::TM_100, false, null, null],
            'null keys, ICE'                       => [$withNullKeys, InternalMatchesConstants::TM_ICE, false, TranslationStatus::STATUS_APPROVED, true],
            'null keys, ICE, pretranslate_100 on'  => [$withNullKeys, InternalMatchesConstants::TM_ICE, true, TranslationStatus::STATUS_APPROVED, true],
            'null keys, 100% pretranslate_100 on'  => [$withNullKeys, InternalMatchesConstants::TM_100, true, TranslationStatus::STATUS_TRANSLATED, false],
            'null keys, 100% pretranslate_100 off' => [$withNullKeys, InternalMatchesConstants::TM_100, false, null, null],
            // a null status marks a legacy element even when the other 101% options are set
            'null status, ICE, 101% off and unlocked' => [
                ['pretranslate_101' => false, 'pretranslate_101_status' => null, 'pretranslate_101_lock' => false],
                InternalMatchesConstants::TM_ICE,
                false,
                TranslationStatus::STATUS_APPROVED,
                true,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[Test]
    #[DataProvider('legacyElementProvider')]
    public function determinePreTranslateStatus_keeps_the_legacy_output_for_elements_without_options(
        array $options,
        string $matchType,
        bool $pretranslate100,
        ?string $expectedStatus,
        ?bool $expectedLocked
    ): void {
        // the real queue Params has no __get: a missing key must be read without a warning
        $params = new Params(array_merge([
            'target'                 => 'it-IT',
            'pretranslate_100'       => $pretranslate100,
            'mt_qe_workflow_enabled' => false,
        ], $options));

        $result = $this->service->determinePreTranslateStatus($this->preConfirmTmData($matchType), $params);

        if ($expectedStatus === null) {
            $this->assertArrayNotHasKey('status', $result);
            $this->assertArrayNotHasKey('locked', $result);
        } else {
            $this->assertSame($expectedStatus, $result['status']);
            $this->assertSame($expectedLocked, $result['locked']);
        }
    }

    /**
     * @return array<string, array{bool|string}>
     */
    public static function pretranslate101OffProvider(): array
    {
        return [
            'false' => [false],
            '"0"'   => ['0'],
        ];
    }

    #[Test]
    #[DataProvider('pretranslate101OffProvider')]
    public function determinePreTranslateStatus_leaves_ice_untouched_when_pretranslate_101_is_off(bool|string $pretranslate101): void
    {
        $params = (object)[
            'target'                  => 'it-IT',
            'pretranslate_100'        => true,
            'mt_qe_workflow_enabled'  => false,
            'pretranslate_101'        => $pretranslate101,
            'pretranslate_101_status' => TranslationStatus::STATUS_APPROVED,
            'pretranslate_101_lock'   => true,
        ];

        $result = $this->service->determinePreTranslateStatus($this->preConfirmTmData(InternalMatchesConstants::TM_ICE), $params);

        $this->assertArrayNotHasKey('status', $result);
        $this->assertArrayNotHasKey('locked', $result);
    }

    #[Test]
    public function determinePreTranslateStatus_applies_the_lock_and_status_defaults_when_only_the_101_status_is_set(): void
    {
        $params = (object)[
            'target'                  => 'it-IT',
            'pretranslate_100'        => true,
            'mt_qe_workflow_enabled'  => false,
            'pretranslate_101_status' => TranslationStatus::STATUS_TRANSLATED,
        ];

        $ice = $this->service->determinePreTranslateStatus($this->preConfirmTmData(InternalMatchesConstants::TM_ICE), $params);
        $this->assertSame(TranslationStatus::STATUS_TRANSLATED, $ice['status']);
        $this->assertTrue($ice['locked']);

        $tm100 = $this->service->determinePreTranslateStatus($this->preConfirmTmData(InternalMatchesConstants::TM_100), $params);
        $this->assertSame(TranslationStatus::STATUS_TRANSLATED, $tm100['status']);
        $this->assertFalse($tm100['locked']);
    }

    #[Test]
    public function determinePreTranslateStatus_skips_option_driven_ice_for_a_disabled_target_language(): void
    {
        Ices::$iceLockDisabledForTargetLangs = ['zh'];

        $params = (object)[
            'target'                  => 'zh-CN',
            'pretranslate_100'        => true,
            'mt_qe_workflow_enabled'  => false,
            'pretranslate_101'        => true,
            'pretranslate_101_status' => TranslationStatus::STATUS_APPROVED2,
            'pretranslate_101_lock'   => true,
            'pretranslate_100_status' => TranslationStatus::STATUS_APPROVED,
            'pretranslate_100_lock'   => true,
        ];

        $ice = $this->service->determinePreTranslateStatus($this->preConfirmTmData(InternalMatchesConstants::TM_ICE), $params);
        $this->assertArrayNotHasKey('status', $ice);
        $this->assertArrayNotHasKey('locked', $ice);

        // the disabled-language list never applied to 100% matches
        $tm100 = $this->service->determinePreTranslateStatus($this->preConfirmTmData(InternalMatchesConstants::TM_100), $params);
        $this->assertSame(TranslationStatus::STATUS_APPROVED, $tm100['status']);
        $this->assertTrue($tm100['locked']);
    }

    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function iceMtLockProvider(): array
    {
        return [
            'lock flag true'    => [['mt_qe_workflow_parameters' => new MTQEWorkflowParams(['lock_best_quality_mt' => true])], true],
            'lock flag false'   => [['mt_qe_workflow_parameters' => new MTQEWorkflowParams(['lock_best_quality_mt' => false])], false],
            'parameters absent' => [[], false],
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[Test]
    #[DataProvider('iceMtLockProvider')]
    public function determinePreTranslateStatus_locks_ice_mt_as_the_mt_qe_parameters_ask(array $options, bool $expectedLocked): void
    {
        $params = new Params(array_merge([
            'target'                 => 'it-IT',
            'pretranslate_100'       => false,
            'mt_qe_workflow_enabled' => true,
        ], $options));

        $result = $this->service->determinePreTranslateStatus(
            ['suggestion_match' => '85%', 'match_type' => InternalMatchesConstants::ICE_MT],
            $params
        );

        $this->assertSame(TranslationStatus::STATUS_APPROVED, $result['status']);
        $this->assertSame($expectedLocked, $result['locked']);
    }

    #[Test]
    public function determinePreTranslateStatus_leaves_ice_mt_untouched_when_mt_qe_is_disabled_whatever_the_lock_flag(): void
    {
        $params = new Params([
            'target'                    => 'it-IT',
            'pretranslate_100'          => false,
            'mt_qe_workflow_enabled'    => false,
            'mt_qe_workflow_parameters' => new MTQEWorkflowParams(['lock_best_quality_mt' => true]),
        ]);

        $result = $this->service->determinePreTranslateStatus(
            ['suggestion_match' => '85%', 'match_type' => InternalMatchesConstants::ICE_MT],
            $params
        );

        $this->assertArrayNotHasKey('status', $result);
        $this->assertArrayNotHasKey('locked', $result);
    }

    #[Test]
    public function determinePreTranslateStatus_reads_the_lock_flag_from_a_queue_element_after_the_broker_round_trip(): void
    {
        $element = new QueueElement();
        $element->classLoad = 'TMAnalysisWorker';
        $element->params = new Params([
            'target'                    => 'it-IT',
            'pretranslate_100'          => false,
            'mt_qe_workflow_enabled'    => true,
            'mt_qe_workflow_parameters' => new MTQEWorkflowParams(['lock_best_quality_mt' => true]),
        ]);

        // the same decode the Executor applies to a frame body
        $decoded = new QueueElement(json_decode((string)json_encode($element), true));
        $this->assertInstanceOf(Params::class, $decoded->params->mt_qe_workflow_parameters);

        $result = $this->service->determinePreTranslateStatus(
            ['suggestion_match' => '85%', 'match_type' => InternalMatchesConstants::ICE_MT],
            $decoded->params
        );

        $this->assertSame(TranslationStatus::STATUS_APPROVED, $result['status']);
        $this->assertTrue($result['locked']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonIceMtBandProvider(): array
    {
        return [
            InternalMatchesConstants::TOP_QUALITY_MT      => [InternalMatchesConstants::TOP_QUALITY_MT],
            InternalMatchesConstants::HIGHER_QUALITY_MT   => [InternalMatchesConstants::HIGHER_QUALITY_MT],
            InternalMatchesConstants::STANDARD_QUALITY_MT => [InternalMatchesConstants::STANDARD_QUALITY_MT],
        ];
    }

    #[Test]
    #[DataProvider('nonIceMtBandProvider')]
    public function determinePreTranslateStatus_never_locks_the_other_mt_qe_bands(string $matchType): void
    {
        $params = new Params([
            'target'                    => 'it-IT',
            'pretranslate_100'          => false,
            'mt_qe_workflow_enabled'    => true,
            'mt_qe_workflow_parameters' => new MTQEWorkflowParams(['lock_best_quality_mt' => true]),
        ]);

        $result = $this->service->determinePreTranslateStatus(
            ['suggestion_match' => '85%', 'match_type' => $matchType],
            $params
        );

        $this->assertArrayNotHasKey('status', $result);
        $this->assertArrayNotHasKey('locked', $result);
    }
}
