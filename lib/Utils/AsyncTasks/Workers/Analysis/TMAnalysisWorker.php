<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/05/15
 * Time: 13.37
 *
 */

namespace Utils\AsyncTasks\Workers\Analysis;

use Controller\API\Commons\Exceptions\AuthenticationError;
use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\Analysis\AnalysisDao;
use Model\Analysis\Constants\InternalMatchesConstants;
use Model\DataAccess\Database;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobDao;
use Model\MTQE\Templates\DTO\MTQEWorkflowParams;
use Model\Projects\ProjectDao;
use Model\Translations\SegmentTranslationDao;
use Model\WordCount\CounterModel;
use PDOException;
use ReflectionException;
use Utils\AsyncTasks\Workers\Traits\MatchesComparator;
use Utils\AsyncTasks\Workers\Traits\ProjectWordCount;
use Utils\Constants\Ices;
use Utils\Constants\ProjectStatus;
use Utils\Constants\TranslationStatus;
use Utils\Engines\AbstractEngine;
use Utils\Engines\EnginesFactory;
use Utils\Engines\MyMemory;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\Logger\Log;
use Utils\LQA\PostProcess;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EmptyElementException;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\NotSupportedMTException;
use Utils\TaskRunner\Exceptions\ReQueueException;
use Utils\TmKeyManagement\TmKeyManager;

/**
 * Class TMAnalysisWorker
 * @package Analysis\Workers
 *
 * Concrete worker.
 * This worker handles a queue element (a segment) and performs the analysis on it
 */
class TMAnalysisWorker extends AbstractWorker {

    use ProjectWordCount;
    use MatchesComparator;

    /**
     * Matches vector
     *
     * @var array|null
     */
    protected ?array $_matches = null;

    const ERR_EMPTY_WORD_COUNT = 4;
    const ERR_WRONG_PROJECT    = 5;

    /**
     * @var FeatureSet
     */
    protected FeatureSet $featureSet;

    /**
     * Concrete Method to start the activity of the worker
     *
     * @param AbstractElement $queueElement
     *
     * @return void
     *
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws Exception
     */
    public function process( AbstractElement $queueElement ) {

        $this->_checkDatabaseConnection();

        /**
         * Ensure we have fresh data from the master node
         */
        $this->featureSet = new FeatureSet();
        /** @var $queueElement QueueElement */
        $this->featureSet->loadFromString( $queueElement->params->features );

        //reset matches vector
        $this->_matches = null;

        /**
         * @var $queueElement QueueElement
         */
        $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Segment {$queueElement->params->id_segment} - Job {$queueElement->params->id_job} found " );

        /**
         * @throws EndQueueException
         */
        $this->_checkForReQueueEnd( $queueElement );

        //START

        $this->_initializeTMAnalysis( $queueElement, $this->_workerPid );

        /**
         * @throws EmptyElementException
         */
        $this->_checkWordCount( $queueElement );

        /**
         * @throws ReQueueException
         * @throws EmptyElementException
         */
        $this->_matches = $this->_getMatches( $queueElement );

        $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Segment {$queueElement->params->id_segment} - Job {$queueElement->params->id_job} matches retrieved." );

        /**
         * @throws ReQueueException
         */
        $this->_updateRecord( $queueElement );
        $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Segment {$queueElement->params->id_segment} - Job {$queueElement->params->id_job} updated." );

        //ack segment
        $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Segment {$queueElement->params->id_segment} - Job {$queueElement->params->id_job} acknowledged." );

    }

    /**
     * @param QueueElement $queueElement
     *
     * @throws EndQueueException
     * @throws ReflectionException
     */
    protected function _endQueueCallback( QueueElement $queueElement ) {
        $this->_forceSetSegmentAnalyzed( $queueElement );
        parent::_endQueueCallback( $queueElement );
    }

    /**
     * Update the record on the database
     *
     * @param QueueElement $queueElement
     *
     * @throws Exception
     * @throws ReQueueException
     */
    protected function _updateRecord( QueueElement $queueElement ) {

        //This function is necessary to prevent TM matches with a value of 75-84% from being overridden by the MT, which has a default value of 86.
        $bestMatch = $this->getHighestNotMT_OrPickTheFirstOne();

        /** @var MatecatFilter $filter */
        $filter     = MateCatFilter::getInstance( $this->featureSet, $queueElement->params->source, $queueElement->params->target );
        $suggestion = $bestMatch[ 'translation' ]; //No layering needed, whe use Layer 1 here

        $equivalentWordMapping = array_change_key_case( json_decode( $queueElement->params->payable_rates, true ), CASE_UPPER );

        [ $fuzzy_band, $discount_value ] = $this->_getNewMatchTypeAndEquivalentWordDiscount(
                $bestMatch,
                $queueElement,
                $equivalentWordMapping
        );

        $eq_words       = $discount_value * $queueElement->params->raw_word_count / 100;
        $standard_words = $eq_words;

        /**
         * if the first match is MT, perform QA realignment because some MT engines break tags
         * also perform a tag ID check and mismatch validation
         */
        if ( in_array( $fuzzy_band, [
                InternalMatchesConstants::MT,
                InternalMatchesConstants::ICE_MT,
                InternalMatchesConstants::TOP_QUALITY_MT,
                InternalMatchesConstants::HIGHER_QUALITY_MT,
                InternalMatchesConstants::STANDARD_QUALITY_MT
        ] ) ) {

            //Reset the standard word count to be equal to other cat tools which do not have the MT in analysis
            $standard_words = ( $equivalentWordMapping[ InternalMatchesConstants::NO_MATCH ] ?? 100 ) * $queueElement->params->raw_word_count / 100;

            // realign MT Spaces
            $check = $this->initPostProcess(
                    $bestMatch[ 'segment' ], // Layer 1 here
                    $suggestion,
                    $queueElement->params->source,
                    $queueElement->params->target
            );
            $check->realignMTSpaces();

        } else {

            // Otherwise, try to perform only the tagCheck
            $check = $this->initPostProcess( $queueElement->params->segment, $suggestion, $queueElement->params->source, $queueElement->params->target );
            $check->performTagCheckOnly();

        }

        //In case of MT matches this should every time be ok because MT preserve tags, but we perform also the check for Memories.
        $err_json = ( $check->thereAreErrors() ) ? $check->getErrorsJSON() : '';

        // perform a consistency check as setTranslation does
        //  to add spaces to translation if needed
        $check = $this->initPostProcess(
                $queueElement->params->segment,
                $suggestion,
                $queueElement->params->source,
                $queueElement->params->target
        );
        $check->performConsistencyCheck();

        if( !$check->thereAreErrors() ){
            $suggestion = $check->getTrgNormalized();
        } else {
            $suggestion = $check->getTargetSeg();
        }

        $err_json2  = ( $check->thereAreErrors() ) ? $check->getErrorsJSON() : '';

        $suggestion = $filter->fromLayer1ToLayer0( $suggestion );

        $suggestion_json = json_encode( $this->_matches );

        $tm_data                             = [];
        $tm_data[ 'id_job' ]                 = $queueElement->params->id_job;
        $tm_data[ 'id_segment' ]             = $queueElement->params->id_segment;
        $tm_data[ 'translation' ]            = $suggestion;
        $tm_data[ 'suggestion' ]             = $suggestion;
        $tm_data[ 'suggestions_array' ]      = $suggestion_json;
        $tm_data[ 'match_type' ]             = strtoupper( $fuzzy_band ); // force the upper case to be consistent (redundant)
        $tm_data[ 'eq_word_count' ]          = ( $eq_words > $queueElement->params->raw_word_count ) ? $queueElement->params->raw_word_count : $eq_words;
        $tm_data[ 'standard_word_count' ]    = ( $standard_words > $queueElement->params->raw_word_count ) ? $queueElement->params->raw_word_count : $standard_words;
        $tm_data[ 'tm_analysis_status' ]     = "DONE";
        $tm_data[ 'warning' ]                = (int)$check->thereAreErrors();
        $tm_data[ 'serialized_errors_list' ] = $this->mergeJsonErrors( $err_json, $err_json2 );
        $tm_data[ 'mt_qe' ]                  = $bestMatch[ 'score' ] ?? null;


        $tm_data[ 'suggestion_source' ] = $bestMatch[ 'created_by' ];
        if ( !empty( $tm_data[ 'suggestion_source' ] ) ) {
            if ( strpos( $tm_data[ 'suggestion_source' ], InternalMatchesConstants::MT ) === false ) {
                $tm_data[ 'suggestion_source' ] = InternalMatchesConstants::TM;
            } else {
                $tm_data[ 'suggestion_source' ] = InternalMatchesConstants::MT;
            }
        }

        //check the value of suggestion_match
        $tm_data[ 'suggestion_match' ] = $bestMatch[ 'match' ];
        $tm_data                       = $this->_lockAndPreTranslateStatusCheck( $tm_data, $queueElement->params );

        try {
            $updateRes = SegmentTranslationDao::setAnalysisValue( $tm_data );
            $message   = ( $updateRes == 0 ) ? "No row found: " . $tm_data[ 'id_segment' ] . "-" . $tm_data[ 'id_job' ] : "Row found: " . $tm_data[ 'id_segment' ] . "-" . $tm_data[ 'id_job' ] . " - UPDATED.";
            $this->_doLog( $message );
        } catch ( Exception $exception ) {
            $this->_doLog( "**** " . $exception->getMessage() );
            $this->_doLog( "**** Error occurred during the storing (UPDATE) of the suggestions for the segment {$tm_data[ 'id_segment' ]}" );
            throw new ReQueueException( "**** Error occurred during the storing (UPDATE) of the suggestions for the segment {$tm_data[ 'id_segment' ]}", self::ERR_REQUEUE );
        }

        //set redis cache
        $this->_incrementAnalyzedCount( $queueElement->params->pid, $eq_words, $standard_words );
        $this->_decSegmentsToAnalyzeOfWaitingProjects( $queueElement->params->pid );
        $this->_tryToCloseProject( $queueElement->params );

    }

    /**
     * Get the first available not MT match
     * @return mixed
     */
    private function getHighestNotMT_OrPickTheFirstOne() {
        foreach ( $this->_matches as $match ) {
            // return $match if not MT and quality >= 75
            if (
                    !$this->isMtMatch( $match ) and
                    (int)$match[ 'match' ] >= 75
            ) {
                return $match;
            }
        }

        // return the first match available
        return $this->_matches[ 0 ];
    }

    /**
     * Init a \PostProcess instance.
     * This method forces to set source/target languages
     *
     * @TODO we may consider to change QA constructor adding source/target languages to it
     *
     * @param $source_seg
     * @param $target_seg
     * @param $source_lang
     * @param $target_lang
     *
     * @return PostProcess
     */
    private function initPostProcess( $source_seg, $target_seg, $source_lang, $target_lang ): PostProcess {
        $check = new PostProcess( $source_seg, $target_seg );
        $check->setFeatureSet( $this->featureSet );
        $check->setSourceSegLang( $source_lang );
        $check->setTargetSegLang( $target_lang );

        return $check;
    }

    /**
     * @param string $err_json
     * @param string $err_json2
     *
     * @return false|string
     */
    private function mergeJsonErrors( string $err_json, string $err_json2 ) {
        if ( $err_json === '' and $err_json2 === '' ) {
            return '';
        }

        if ( $err_json !== '' and $err_json2 === '' ) {
            return $err_json;
        }

        if ( $err_json === '' and $err_json2 !== '' ) {
            return $err_json2;
        }

        return json_encode( array_merge_recursive( json_decode( $err_json, true ), json_decode( $err_json2, true ) ) );
    }

    /**
     * @param array  $tm_data
     * @param Params $queueElementParams
     *
     * @return array
     */
    protected function _lockAndPreTranslateStatusCheck( array $tm_data, Params $queueElementParams ): array {

        //Separates if branches to make the conditions more readable
        if ( stripos( $tm_data[ 'suggestion_match' ], InternalMatchesConstants::TM_100 ) !== false ) {

            if ( $tm_data[ 'match_type' ] == InternalMatchesConstants::TM_ICE ) {

                [ $lang, ] = explode( '-', $queueElementParams->target );

                //I found this language in the list of disabled target languages??
                if ( !in_array( $lang, ICES::$iceLockDisabledForTargetLangs ) ) {
                    //ice lock enabled, language not found
                    $tm_data[ 'status' ] = TranslationStatus::STATUS_APPROVED;
                    $tm_data[ 'locked' ] = true;
                }

            } elseif ( $queueElementParams->pretranslate_100 ) {
                $tm_data[ 'status' ] = TranslationStatus::STATUS_TRANSLATED;
                $tm_data[ 'locked' ] = false;
            }

        }

        if ( $queueElementParams->mt_qe_workflow_enabled && $tm_data[ 'match_type' ] == InternalMatchesConstants::ICE_MT ) {
            $tm_data[ 'status' ] = TranslationStatus::STATUS_APPROVED;
            $tm_data[ 'locked' ] = false;
        }

        return $tm_data;

    }

    /**
     * Calculate the new score match by the Equivalent word mapping (the value is inside the queue element)
     *
     * RATIO: I change the value only if the new match is strictly better
     * (in terms of percent paid per word) than the actual one
     *
     *
     * @param array        $bestMatch
     * @param QueueElement $queueElement
     * @param array        $equivalentWordMapping
     *
     * @return array
     */
    protected function _getNewMatchTypeAndEquivalentWordDiscount(
            array        $bestMatch,
            QueueElement $queueElement,
            array        $equivalentWordMapping
    ): array {

        $tm_match_type         = ( $this->isMtMatch( $bestMatch ) ? InternalMatchesConstants::MT : $bestMatch[ 'match' ] );
        $fast_match_type       = strtoupper( $queueElement->params->match_type );
        $fast_exact_match_type = $queueElement->params->fast_exact_match_type;

        /* is Public TM */
        $publicTM = empty( $bestMatch[ 'memory_key' ] );
        $isICE    = isset( $bestMatch[ InternalMatchesConstants::TM_ICE ] ) && $bestMatch[ InternalMatchesConstants::TM_ICE ];

        // When MTQE is enabled, the NO_MATCH and INTERNAL types are not defined in the payable rates. So fall back to the 100% rate, since it is overwritten by design.
        $fast_rate_paid = $equivalentWordMapping[ $fast_match_type ] ?? 100;

        $tm_match_fuzzy_band = "";
        $tm_discount         = 0;
        $ind                 = null;

        if ( stripos( $tm_match_type, InternalMatchesConstants::MT ) !== false ) {

            if ( !empty( $bestMatch[ 'score' ] ) && $bestMatch[ 'score' ] >= 0.9 ) {
                $tm_match_fuzzy_band = InternalMatchesConstants::ICE_MT;
                $tm_discount         = $equivalentWordMapping[ InternalMatchesConstants::ICE_MT ];
            } else {

                if ( !$queueElement->params->mt_qe_workflow_enabled ) { // default behaviour
                    $tm_match_fuzzy_band = InternalMatchesConstants::MT;
                    $tm_discount         = $equivalentWordMapping[ InternalMatchesConstants::MT ]; // set all scores as generic MT
                } else {

                    // set values for MTQEPayableRateBreakdowns
                    if ( $bestMatch[ 'score' ] >= 0.8 ) {
                        $tm_match_fuzzy_band = InternalMatchesConstants::TOP_QUALITY_MT;
                        $tm_discount         = $equivalentWordMapping[ InternalMatchesConstants::TOP_QUALITY_MT ];
                    } elseif ( $bestMatch[ 'score' ] >= 0.5 ) {
                        $tm_match_fuzzy_band = InternalMatchesConstants::HIGHER_QUALITY_MT;
                        $tm_discount         = $equivalentWordMapping[ InternalMatchesConstants::HIGHER_QUALITY_MT ];
                    } else {
                        $tm_match_fuzzy_band = InternalMatchesConstants::STANDARD_QUALITY_MT;
                        $tm_discount         = $equivalentWordMapping[ InternalMatchesConstants::STANDARD_QUALITY_MT ];
                    }

                }

            }

        } else {

            $ind = intval( $tm_match_type );

            if ( $ind == 100 ) {

                if ( $isICE ) {
                    $tm_match_fuzzy_band = InternalMatchesConstants::TM_ICE;
                    $tm_discount         = ( isset( $equivalentWordMapping[ $tm_match_fuzzy_band ] ) ) ? $equivalentWordMapping[ $tm_match_fuzzy_band ] : null;
                } else {

                    $tm_match_fuzzy_band = $temp_tm_match_fuzzy_band = ( $publicTM ) ? InternalMatchesConstants::TM_100_PUBLIC : InternalMatchesConstants::TM_100;

                    if ( $queueElement->params->mt_qe_workflow_enabled ) {
                        $temp_tm_match_fuzzy_band = ( $publicTM ) ? InternalMatchesConstants::TM_100_PUBLIC_MT_QE : InternalMatchesConstants::TM_100_MT_QE;
                    }

                    $tm_discount = $equivalentWordMapping[ $temp_tm_match_fuzzy_band ];
                }

            }

            /**
             * Match never returns matches below 50%, it sends them as NO_MATCH,
             * So this block of code results unused
             */
            if ( $ind < 50 ) {
                $tm_match_fuzzy_band = InternalMatchesConstants::NO_MATCH;
                $tm_discount         = $equivalentWordMapping[ InternalMatchesConstants::NO_MATCH ];
            }

            if ( $ind >= 50 and $ind < 75 ) {
                $tm_match_fuzzy_band = InternalMatchesConstants::TM_50_74;
                $tm_discount         = $equivalentWordMapping[ InternalMatchesConstants::TM_50_74 ];
            }

            if ( $ind >= 75 && $ind <= 84 ) {
                $tm_match_fuzzy_band = InternalMatchesConstants::TM_75_84;
                $tm_discount         = $equivalentWordMapping[ InternalMatchesConstants::TM_75_84 ];
            } elseif ( $ind >= 85 && $ind <= 94 ) {
                $tm_match_fuzzy_band = InternalMatchesConstants::TM_85_94;
                $tm_discount         = $equivalentWordMapping[ InternalMatchesConstants::TM_85_94 ];
            } elseif ( $ind >= 95 && $ind <= 99 ) {
                $tm_match_fuzzy_band = InternalMatchesConstants::TM_95_99;
                $tm_discount         = $equivalentWordMapping[ InternalMatchesConstants::TM_95_99 ];
            }

        }

        // if MM says is ICE, return ICE
        if ( $isICE ) {
            return [ $tm_match_fuzzy_band, $tm_discount ];
        }

        // if there is a repetition with a 100% match type, return 100%
        if ( $ind == 100 && $fast_match_type == InternalMatchesConstants::REPETITIONS ) {
            return [ $tm_match_fuzzy_band, $tm_discount ];
        }

        // if there is a repetition from Fast, keep it in the REPETITIONS bucket
        if ( $fast_match_type == InternalMatchesConstants::REPETITIONS ) {
            return [ $fast_match_type, $equivalentWordMapping[ $fast_match_type ] ];
        }

        // if Fast match type > TM match type, return it
        // otherwise return the TM match type
        if ( $fast_match_type === InternalMatchesConstants::INTERNAL && !$queueElement->params->mt_qe_workflow_enabled ) {
            $ind_fast = intval( $fast_exact_match_type );

            if ( $ind_fast > $ind ) {
                return [ $fast_match_type, $equivalentWordMapping[ $fast_match_type ] ];
            }

            return [ $tm_match_fuzzy_band, $tm_discount ];
        }

        /**
         * Apply the TM discount rate and/or force the value obtained from TM for
         * matches between 50%-74% because is never returned in Fast Analysis; it's rate is set default as equals to NO_MATCH
         */
        if (
                in_array( $fast_match_type, [ InternalMatchesConstants::INTERNAL, InternalMatchesConstants::REPETITIONS ] )
                && $tm_discount <= $fast_rate_paid
                || $fast_match_type == InternalMatchesConstants::NO_MATCH
        ) {
            return [ $tm_match_fuzzy_band, $tm_discount ];
        }

        return [ $fast_match_type, $equivalentWordMapping[ $fast_match_type ] ];
    }

    /**
     * Get matches from Match and other engines
     *
     * @param $queueElement QueueElement
     *
     * @return array
     * @throws Exception
     */
    protected function _getMatches( QueueElement $queueElement ): array {

        $_config              = [];
        $_config[ 'pid' ]     = $queueElement->params->pid;
        $_config[ 'segment' ] = $queueElement->params->segment;
        $_config[ 'source' ]  = $queueElement->params->source;
        $_config[ 'target' ]  = $queueElement->params->target;
        $_config[ 'email' ]   = AppConfig::$MYMEMORY_TM_API_KEY;

        $_config[ 'context_before' ]    = $queueElement->params->context_before;
        $_config[ 'context_after' ]     = $queueElement->params->context_after;
        $_config[ 'additional_params' ] = $queueElement->params->additional_params ?? null;
        $_config[ 'priority_key' ]      = $queueElement->params->tm_prioritization ?? null;
        $_config[ 'job_id' ]            = $queueElement->params->id_job ?? null;

        if ( $queueElement->params->dialect_strict ?? false ) { //null coalesce operator when dialect_strict is not set
            $_config[ 'dialect_strict' ] = $queueElement->params->dialect_strict;
        }

        // public_tm_penalty
        if ( !empty( $queueElement->params->public_tm_penalty ) ) {
            $_config[ 'public_tm_penalty' ] = $queueElement->params->public_tm_penalty;
        }

        // penalty_key
        $penalty_key = [];
        $tm_keys     = TmKeyManager::getJobTmKeys( $queueElement->params->tm_keys, 'r' );

        if ( !empty( $tm_keys ) ) {
            foreach ( $tm_keys as $tm_key ) {
                $_config[ 'id_user' ][] = $tm_key->key;

                if ( isset( $tm_key->penalty ) ) {
                    $penalty_key[] = $tm_key->penalty;
                } else {
                    $penalty_key[] = 0;
                }
            }
        }

        if ( !empty( $penalty_key ) ) {
            $_config[ 'penalty_key' ] = $penalty_key;
        }

        $_config[ 'num_result' ] = 3;

        $id_mt_engine = $queueElement->params->id_mt_engine;
        $id_tms       = $queueElement->params->id_tms;

        $tmsEngine = EnginesFactory::getInstance( $id_tms );
        $mtEngine  = EnginesFactory::getInstance( $id_mt_engine );

        if ( $mtEngine instanceof MyMemory ) {

            $_config[ 'get_mt' ] = true;
            $mtEngine            = EnginesFactory::getInstance( 0 );  //Do Not Call Match with this instance, use $tmsEngine instance

        } else {
            $_config[ 'get_mt' ] = false;
        }

        if ( $queueElement->params->only_private ) {
            $_config[ 'onlyprivate' ] = true; // Match configuration, get matches only from private memories
        }

        // if we want only private tm with no keys, mymemory should not be called
        if ( $queueElement->params->only_private && empty( $_config[ 'id_user' ] ) && !$_config[ 'get_mt' ] ) {
            $tmsEngine = EnginesFactory::getInstance( 0 );
        }

        /*
         * This will be ever executed without damages because
         * fastAnalysis set Project as DONE when
         * Match is disabled and MT is Disabled Too
         *
         * So don't worry, perform TMS Analysis
         *
         */
        $matches = [];

        $mt_qe_config = null;

        if ( $queueElement->params->mt_qe_workflow_enabled ) {
            // Initialize the MTQEWorkflowParams object with the workflow parameters from the queue element.
            $mt_qe_config = new MTQEWorkflowParams( json_decode( $queueElement->params->mt_qe_workflow_parameters ?? null, true ) ?? [] ); // params or default configuration (NULL safe)
        }

        try {

            $tms_match = $this->__filterTMMatches( $this->_getTM( $tmsEngine, $_config, $queueElement ), $queueElement->params->mt_qe_workflow_enabled, $mt_qe_config );
            if ( !empty( $tms_match ) ) {
                $matches = $tms_match;
            }

        } catch ( ReQueueException $rEx ) {
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") : RequeueException: " . $rEx->getMessage() );
            $this->_forceSetSegmentAnalyzed( $queueElement );
            throw $rEx;  // just to make code more readable, re-throw exception
        } catch ( NotSupportedMTException $nMTEx ) {
            // Do nothing, skip the frame
        }

        $mt_result = $this->_getMT( $mtEngine, $_config, $queueElement, $mt_qe_config );

        $matches = $this->_sortMatches( $mt_result, $matches );

        /**
         * If No results found. Ack and Continue
         */
        if ( empty( $matches ) ) {
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") : No contribution found for this segment." );
            $this->_forceSetSegmentAnalyzed( $queueElement );
            throw new EmptyElementException( "--- (Worker " . $this->_workerPid . ") : No contribution found for this segment.", self::ERR_EMPTY_ELEMENT );
        }

        return $matches;

    }

    /**
     * Filters Translation Memory (TM) matches based on specific criteria defined in the MTQE workflow parameters.
     *
     * @param array                   $matches An array of TM matches to be filtered.
     * @param bool                    $mt_qe_workflow_enabled
     * @param MTQEWorkflowParams|null $mt_qe_config
     *
     * @return array The filtered array of TM matches.
     */
    private function __filterTMMatches( array $matches, bool $mt_qe_workflow_enabled, ?MTQEWorkflowParams $mt_qe_config ): array {

        // Filter the matches array using a callback function.
        return array_filter( $matches, function ( $match ) use ( $mt_qe_config, $mt_qe_workflow_enabled ) {

            // Check if the MTQE workflow is enabled.
            if ( $mt_qe_workflow_enabled ) {

                // If the "analysis_ignore_101" flag is set, ignore all matches.
                if ( $mt_qe_config->analysis_ignore_101 ) {
                    return false;
                }

                // If the "analysis_ignore_100" flag is set, ignore matches with a score <= 100 unless they are ICE matches.
                if ( $mt_qe_config->analysis_ignore_100 ) {
                    if ( (int)$match[ 'match' ] <= 100 && !$match[ InternalMatchesConstants::TM_ICE ] ) {
                        return false;
                    }
                }

                // By definition, ignore all matches with a score below 100 when the MTQE workflow is enabled.
                if ( (int)$match[ 'match' ] < 100 ) {
                    return false;
                }

            }

            // If none of the conditions above are met, include the match.
            return true;

        } );

    }

    /**
     * Call External MT engine if it is custom (mt not requested from Match)
     *
     * @param AbstractEngine          $mtEngine
     * @param array                   $_config
     *
     * @param QueueElement            $queueElement
     * @param MTQEWorkflowParams|null $mt_qe_config
     *
     * @return array
     */
    protected function _getMT( AbstractEngine $mtEngine, array $_config, QueueElement $queueElement, ?MTQEWorkflowParams $mt_qe_config ): array {

        $mt_result = [];

        try {

            $mtEngine->setFeatureSet( $this->featureSet );

            //tell to the engine that this is the analysis phase (some engines want to skip the analysis)
            $mtEngine->setAnalysis();

            // If mt_qe_workflow_enabled is true, force set EnginesFactory.skipAnalysis to `false` to allow the Lara engine to perform the analysis.
            if ( $queueElement->params->mt_qe_workflow_enabled ) {
                $mtEngine->setSkipAnalysis( false );
                $_config[ 'mt_qe_engine_id' ] = $mt_qe_config->qe_model_version;
            }

            $config = $mtEngine->getConfigStruct();
            $config = array_merge( $config, $_config );

            $mtEngine->setMTPenalty( $queueElement->params->mt_quality_value_in_editor ? 100 - $queueElement->params->mt_quality_value_in_editor : null ); // can be (100-102 == -2). In AbstractEngine it will be set as (100 - -2 == 102);

            // set for lara engine in case this is needed to catch all owner keys
            $config[ 'all_job_tm_keys' ] = $queueElement->params->tm_keys;
            $config[ 'include_score' ]   = $queueElement->params->mt_evaluation ?? false;

            if ( !isset( $config[ 'job_id' ] ) ) {
                $config[ 'job_id' ] = $queueElement->params->id_job;
            }

            // if a callback is not set, only the first argument is returned, get the config params from the callback
            $config = $this->featureSet->filter( 'analysisBeforeMTGetContribution', $config, $mtEngine, $queueElement ); //YYY verify airbnb plugin and MMT engine, such plugin force to use MMT, but MMT now is enabled by default

            $mt_result = $mtEngine->get( $config );

            // handle GetMemoryResponse instead of having directly Matches
            if ( $mt_result instanceof GetMemoryResponse ) {
                if ( isset( $mt_result->responseStatus ) && $mt_result->responseStatus >= 400 ) {
                    return [];
                }
                $mt_result = $mt_result->get_matches_as_array( 1 );
                $mt_result = $mt_result[ 'matches' ][ 0 ] ?? [];
            }

            if ( isset( $mt_result[ 'error' ][ 'code' ] ) ) {
                return [];
            }

        } catch ( Exception $e ) {
            $this->_doLog( $e->getMessage() );
        }

        return $mt_result;

    }

    /**
     * @param AbstractEngine                $tmsEngine
     * @param                               $_config
     * @param QueueElement                  $queueElement
     *
     * @return array|GetMemoryResponse|null
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws NotSupportedMTException
     * @throws ReQueueException
     * @throws ValidationError
     * @throws Exception
     */
    protected function _getTM( AbstractEngine $tmsEngine, $_config, QueueElement $queueElement ) {

        /**
         * @var $tmsEngine MyMemory
         */
        $tmsEngine->setFeatureSet( $this->featureSet );

        $config = $tmsEngine->getConfigStruct();
        $config = array_merge( $config, $_config );

        $tmsEngine->setMTPenalty( $queueElement->params->mt_quality_value_in_editor ? 100 - $queueElement->params->mt_quality_value_in_editor : null ); // can be (100-102 == -2). In AbstractEngine it will be set as (100 - -2 == 102);

        /** @var $tms_match GetMemoryResponse */
        $tms_match = $tmsEngine->get( $config );

        /**
         * If No results found. Re-Queue
         *
         * Match can return null if an error occurs (e.g., http response code is 404, 410, 500, 503, etc...)
         */
        if ( !empty( $tms_match->error ) ) {
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Error from Match. NULL received." );
            throw new ReQueueException( "--- (Worker " . $this->_workerPid . ") : Error from Match. NULL received.", self::ERR_REQUEUE );
        }

        if ( !$tms_match->mtLangSupported ) {
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Error from Match. MT not supported." );
            throw new NotSupportedMTException( "--- (Worker " . $this->_workerPid . ") : Error from Match. MT not supported.", self::ERR_EMPTY_ELEMENT );
        }

        // Strict check for MT engine == 1, this means we requested Match explicitly to get MT (the returned record cannot be empty). Try again
        if ( empty( $tms_match ) && $_config[ 'get_mt' ] ) {
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") : Error from Match. Empty field received even if MT was requested." );
            throw new ReQueueException( "--- (Worker " . $this->_workerPid . ") : Error from Match. Empty field received even if MT was requested.", self::ERR_REQUEUE );
        }

        if ( !empty( $tms_match ) ) {
            $tms_match = $tms_match->get_matches_as_array( 1 );
        }

        return $tms_match;

    }

    /**
     * Check for a relevant word count, otherwise de-queue the segment and set as done
     *
     * @param QueueElement $queueElement
     *
     * @throws Exception
     */
    protected function _checkWordCount( QueueElement $queueElement ) {

        if ( $queueElement->params->raw_word_count == 0 ) {
//            SET as DONE and "decrement counter/close project"
            $this->_forceSetSegmentAnalyzed( $queueElement );
            $this->_doLog( "--- (Worker " . $this->_workerPid . ") : empty word count segment. acknowledge and continue." );
            throw new EmptyElementException( "--- (Worker " . $this->_workerPid . ") : empty segment. acknowledge and continue", self::ERR_EMPTY_WORD_COUNT );
        }

    }

    /**
     * Initialize the counter for the analysis.
     * Take the info from the project and initialize it.
     * There is a lock for every project on redis, so only one worker can initialize the counter
     *
     *  - Set the project total segments to analyze and count the analyzed as segments done
     *
     * @param QueueElement $queueElement
     * @param string       $process_pid
     *
     * @throws ReflectionException
     */
    protected function _initializeTMAnalysis( QueueElement $queueElement, string $process_pid ) {

        $sid = $queueElement->params->id_segment;
        $jid = $queueElement->params->id_job;
        $pid = $queueElement->params->pid;

        //get the number of segments in a job
        $_acquiredLock = $this->_queueHandler->getRedisClient()->setnx( RedisKeys::PROJECT_INIT_SEMAPHORE . $pid, true ); // lock for 24 hours
        if ( !empty( $_acquiredLock ) ) {

            $this->_queueHandler->getRedisClient()->expire( RedisKeys::PROJECT_INIT_SEMAPHORE . $pid, 60 * 60 * 24 /* 24 hours TTL */ );

            // Get those data from the master database to avoid delayed replication issues
            $db = Database::obtain();
            $db->begin();
            $total_segments = $this->getProjectSegmentsTranslationSummary( $pid );
            $db->commit();

            $total_segments = array_pop( $total_segments ); // get the Rollup Value
            $this->_doLog( $total_segments );

            $this->_queueHandler->getRedisClient()->setex( RedisKeys::PROJECT_TOT_SEGMENTS . $pid, 60 * 60 * 24 /* 24 hours TTL */, $total_segments[ 'project_segments' ] );
            $this->_queueHandler->getRedisClient()->incrby( RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid, $total_segments[ 'num_analyzed' ] );
            $this->_queueHandler->getRedisClient()->expire( RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid, 60 * 60 * 24 /* 24 hours TTL */ );
            $this->_doLog( "--- (Worker $process_pid) : found " . $total_segments[ 'project_segments' ] . " segments for PID $pid" );

        } else {
            $_projectTotSegments = $this->_queueHandler->getRedisClient()->get( RedisKeys::PROJECT_TOT_SEGMENTS . $pid );
            $_analyzed           = $this->_queueHandler->getRedisClient()->get( RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid );
            $this->_doLog( "--- (Worker $process_pid) : found $_projectTotSegments, analyzed $_analyzed segments for PID $pid in Redis" );
        }

        $this->_doLog( "--- (Worker $process_pid) : fetched data for segment $sid-$jid. Project ID is $pid" );

    }

    /**
     * Increment the analysis counter:
     *  - eq_word_count
     *  - st_word_count
     *  - num_segments_done
     *
     * @param $pid
     * @param $eq_words
     * @param $standard_words
     *
     * @throws ReflectionException
     */
    protected function _incrementAnalyzedCount( $pid, $eq_words, $standard_words ) {
        $this->_queueHandler->getRedisClient()->incrby( RedisKeys::PROJ_EQ_WORD_COUNT . $pid, (int)( $eq_words * 1000 ) );
        $this->_queueHandler->getRedisClient()->incrby( RedisKeys::PROJ_ST_WORD_COUNT . $pid, (int)( $standard_words * 1000 ) );
        $this->_queueHandler->getRedisClient()->incrby( RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $pid, 1 );
    }

    /**
     * Decrement the number of segments that we must wait before that this project starts.
     * There is a list of project ids from which the interface will read the remaining segments.
     *
     * @param int $project_id
     *
     * @throws Exception
     */
    protected function _decSegmentsToAnalyzeOfWaitingProjects( int $project_id ) {

        if ( empty( $project_id ) ) {
            throw new Exception( 'Can Not send without a Queue ID. \Analysis\QueueHandler::setQueueID ', self::ERR_WRONG_PROJECT );
        }

        $working_jobs = $this->_queueHandler->getRedisClient()->lrange( $this->_myContext->redis_key, 0, -1 );

        /**
         * We have an unordered list of numeric keys [1,3,2,5,4]
         *
         * I want to decrement the key positioned in the list after my key.
         *
         * So, if my key is 2, I want to not decrement key 3 in the example because my key is positioned after "3" in the list
         *
         */
        $found = false;
        foreach ( $working_jobs as $value ) {
            if ( $value == $project_id ) {
                $found = true;
            }
            if ( $found ) {
                $this->_queueHandler->getRedisClient()->decr( RedisKeys::TOTAL_SEGMENTS_TO_WAIT . $value );
            }
        }

    }

    /**
     * @param $_params
     *
     * @throws ReflectionException
     */
    protected function _tryToCloseProject( $_params ) {

        $_project_id = $_params->pid;

        $project_totals                       = [];
        $project_totals[ 'project_segments' ] = $this->_queueHandler->getRedisClient()->get( RedisKeys::PROJECT_TOT_SEGMENTS . $_project_id );
        $project_totals[ 'num_analyzed' ]     = $this->_queueHandler->getRedisClient()->get( RedisKeys::PROJECT_NUM_SEGMENTS_DONE . $_project_id );
        $project_totals[ 'eq_wc' ]            = $this->_queueHandler->getRedisClient()->get( RedisKeys::PROJ_EQ_WORD_COUNT . $_project_id ) / 1000;
        $project_totals[ 'st_wc' ]            = $this->_queueHandler->getRedisClient()->get( RedisKeys::PROJ_ST_WORD_COUNT . $_project_id ) / 1000;

        $this->_doLog( "--- (Worker $this->_workerPid) : count segments in project $_project_id = " . $project_totals[ 'project_segments' ] );
        $this->_doLog( "--- (Worker $this->_workerPid) : Analyzed segments in project $_project_id = " . $project_totals[ 'num_analyzed' ] );

        if ( empty( $project_totals[ 'project_segments' ] ) ) {
            $this->_doLog( "--- (Worker $this->_workerPid) : WARNING !!! error while counting segments in projects $_project_id skipping and continue " );

            return;
        }

        if ( $project_totals[ 'project_segments' ] - $project_totals[ 'num_analyzed' ] == 0 && $this->_queueHandler->getRedisClient()->setnx( RedisKeys::PROJECT_ENDING_SEMAPHORE . $_project_id, 1 ) ) {

            $this->_queueHandler->getRedisClient()->expire( RedisKeys::PROJECT_ENDING_SEMAPHORE . $_project_id, 60 * 60 * 24 /* 24 hours TTL */ );

            /*
             * Remove this job from the project list
             */
            $this->_queueHandler->getRedisClient()->lrem( $this->_myContext->redis_key, 0, $_project_id );

            $this->_doLog( "--- (Worker $this->_workerPid) : trying to initialize job total word count." );

            $database = Database::obtain();
            $database->begin();

            $_analyzed_report = $this->getProjectSegmentsTranslationSummary( $_project_id );

            array_pop( $_analyzed_report ); //remove Rollup

            $this->_doLog( "--- (Worker $this->_workerPid) : analysis project $_project_id finished : change status to DONE" );

            ProjectDao::updateFields(
                    [
                            'status_analysis'      => ProjectStatus::STATUS_DONE,
                            'tm_analysis_wc'       => $project_totals[ 'eq_wc' ],
                            'standard_analysis_wc' => $project_totals[ 'st_wc' ]
                    ],
                    [ 'id' => $_project_id ]
            );

            // update chunks' standard_analysis_wc
            $jobs         = ProjectDao::findById( $_project_id )->getChunks();
            $numberOfJobs = count( $jobs );

            foreach ( $jobs as $job ) {
                JobDao::updateFields( [
                        'standard_analysis_wc' => round( $project_totals[ 'st_wc' ] / $numberOfJobs )
                ], [
                        'id' => $job->id
                ] );
            }

            foreach ( $_analyzed_report as $job_info ) {
                $counter = new CounterModel();
                $counter->initializeJobWordCount( $job_info[ 'id_job' ], $job_info[ 'password' ] );
            }

            $database->commit();

            try {
                $this->featureSet->run( 'afterTMAnalysisCloseProject', $_project_id, $_analyzed_report );
            } catch ( Exception $e ) {
                //ignore Exception the analysis is finished anyway
                $this->_doLog( "Ending project_id $_project_id with error {$e->getMessage()} . COMPLETED." );
            }

            ( new JobDao() )->destroyCacheByProjectId( $_project_id );
            ProjectDao::destroyCacheById( $_project_id );
            ProjectDao::destroyCacheByIdAndPassword( $_project_id, $_params->ppassword );
            AnalysisDao::destroyCacheByProjectId( $_project_id );

        }

    }

    /**
     * When a segment has an error or was re-queued too many times, we want to force it as analyzed
     *
     * @param $elementQueue QueueElement
     *
     * @throws ReflectionException
     * @throws Exception
     */
    protected function _forceSetSegmentAnalyzed( QueueElement $elementQueue ) {

        $data[ 'tm_analysis_status' ] = "DONE"; // DONE. I don't want it to remain in an inconsistent state
        $where                        = [
                "id_segment" => $elementQueue->params->id_segment,
                "id_job"     => $elementQueue->params->id_job
        ];

        $db = Database::obtain();
        try {
            $db->update( 'segment_translations', $data, $where );
        } catch ( PDOException $e ) {
            $this->_doLog( $e->getMessage() );
        }

        $this->_incrementAnalyzedCount( $elementQueue->params->pid, $elementQueue->params->raw_word_count, $elementQueue->params->raw_word_count );
        $this->_decSegmentsToAnalyzeOfWaitingProjects( $elementQueue->params->pid );
        $this->_tryToCloseProject( $elementQueue->params );

    }

}
