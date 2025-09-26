<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/05/16
 * Time: 20.36
 *
 */

namespace Utils\AsyncTasks\Workers;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\MTQE\Templates\DTO\MTQEWorkflowParams;
use Model\Projects\MetadataDao;
use Model\Translations\SegmentTranslationDao;
use Model\Users\UserStruct;
use ReflectionException;
use Utils\AsyncTasks\Workers\Traits\MatchesComparator;
use Utils\Constants\EngineConstants;
use Utils\Constants\TranslationStatus;
use Utils\Contribution\GetContributionRequest;
use Utils\Engines\Results\MyMemory\GetMemoryResponse;
use Utils\LQA\PostProcess;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Commons\AbstractElement;
use Utils\TaskRunner\Commons\AbstractWorker;
use Utils\TaskRunner\Commons\QueueElement;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;
use Utils\TmKeyManagement\TmKeyManager;
use Utils\Tools\Utils;

class GetContributionWorker extends AbstractWorker {

    use MatchesComparator;

    /**
     * @param AbstractElement $queueElement
     *
     * @return void
     * @throws EndQueueException
     * @throws Exception
     */
    public function process( AbstractElement $queueElement ) {

        /**
         * @var $queueElement QueueElement
         */
        $this->_checkForReQueueEnd( $queueElement );

        $contributionStruct = new GetContributionRequest( $queueElement->params->toArray() );

        $this->_checkDatabaseConnection();

        $this->_execGetContribution( $contributionStruct );

    }

    /**
     * @param GetContributionRequest $contributionStruct
     *
     * @throws Exception
     */
    protected function _execGetContribution( GetContributionRequest $contributionStruct ) {

        $jobStruct = $contributionStruct->getJobStruct();

        $featureSet = new FeatureSet();
        $featureSet->loadForProject( $contributionStruct->getProjectStruct() );

        [ $mt_result, $matches ] = $this->_getMatches( $contributionStruct, $jobStruct, $jobStruct->target, $featureSet );

        $matches = $this->_sortMatches( $mt_result, $matches );

        if ( !$contributionStruct->concordanceSearch ) {
            //execute these lines only in segment contribution search,
            //in case of user concordance search skip these lines
            $this->updateAnalysisSuggestion( $matches, $contributionStruct );
        }

        $matches = array_slice( $matches, 0, $contributionStruct->resultNum );
        $this->normalizeMTMatches( $matches, $contributionStruct, $featureSet );

        $this->_publishPayload( $matches, $contributionStruct, $featureSet, $jobStruct->target );

        // cross-language matches
        if ( !empty( $contributionStruct->crossLangTargets ) ) {
            $crossLangMatches = [];

            foreach ( $contributionStruct->crossLangTargets as $lang ) {

                // double check for not black lang
                if ( $lang !== '' ) {
                    [ , $matches ] = $this->_getMatches( $contributionStruct, $jobStruct, $lang, $featureSet, true );

                    $matches = array_slice( $matches, 0, $contributionStruct->resultNum );
                    $this->normalizeMTMatches( $matches, $contributionStruct, $featureSet );

                    foreach ( $matches as $match ) {
                        $crossLangMatches[] = $match;
                    }
                }
            }

            if ( !empty( $crossLangMatches ) ) {
                usort( $crossLangMatches, [ "self", "__compareScoreDesc" ] );
            }

            if ( false === $contributionStruct->concordanceSearch ) {
                $this->_publishPayload( $crossLangMatches, $contributionStruct, $featureSet, $jobStruct->target, true );
            }
        }
    }

    /**
     * @param array                  $content
     * @param GetContributionRequest $contributionStruct
     * @param FeatureSet             $featureSet
     * @param                        $targetLang
     * @param bool                   $isCrossLang
     *
     * @throws Exception
     */
    protected function _publishPayload( array $content, GetContributionRequest $contributionStruct, FeatureSet $featureSet, $targetLang, ?bool $isCrossLang = false ) {

        $type = 'contribution';

        if ( $contributionStruct->concordanceSearch ) {
            $type = 'concordance';
        }

        if ( $isCrossLang ) {
            $type = 'cross_language_matches';
        }

        $jobStruct = $contributionStruct->getJobStruct();

        /** @var MateCatFilter $Filter */
        $Filter = MateCatFilter::getInstance(
                $featureSet,
                $jobStruct->source,
                $targetLang,
                $contributionStruct->dataRefMap
        );

        foreach ( $content as &$match ) {

            if ( $match[ 'created_by' ] == 'MT!' ) {
                $match[ 'created_by' ] = EngineConstants::MT; //Match returns MT!
            }

            // Convert &#10; to layer2 placeholder for the UI
            // Those strings are on layer 1, force the transition to layer 2.
            $match[ 'segment' ]     = $Filter->fromLayer1ToLayer2( $match[ 'segment' ] );
            $match[ 'translation' ] = $Filter->fromLayer1ToLayer2( $match[ 'translation' ] );
        }

        $_object = [
                '_type' => $type,
                'data'  => [
                        'id_job'    => $contributionStruct->getJobStruct()->id,
                        'passwords' => $contributionStruct->getJobStruct()->password,
                        'payload'   => [
                                'id_segment' => (string)$contributionStruct->segmentId,
                                'matches'    => $content,
                        ],
                        'id_client' => $contributionStruct->id_client,
                ]
        ];

        $this->publishToNodeJsClients( $_object );
        $this->_doLog( json_encode( $_object ) );

    }


    /**
     * @throws Exception
     */
    protected function _extractAvailableKeysForUser( GetContributionRequest $contributionStruct ): array {

        //find all the job's TMs with write grants and make a contribution to them
        $tm_keys = TmKeyManager::getJobTmKeys( $contributionStruct->getJobStruct()->tm_keys, 'r', 'tm', $contributionStruct->getUser()->uid, $contributionStruct->userRole );

        $keyList = [];
        if ( !empty( $tm_keys ) ) {
            foreach ( $tm_keys as $tm_info ) {
                $keyList[] = $tm_info->key;
            }
        }

        return $keyList;

    }

    /**
     * @param array                  $matches
     * @param GetContributionRequest $contributionStruct
     * @param FeatureSet             $featureSet
     *
     * @throws Exception
     */
    public function normalizeMTMatches( array &$matches, GetContributionRequest $contributionStruct, FeatureSet $featureSet ) {

        foreach ( $matches as &$match ) {

            if ( $this->isMtMatch( $match ) ) {

                $match[ 'match' ] = EngineConstants::MT;

                $QA = new PostProcess( $match[ 'segment' ], $match[ 'translation' ] ); // layer 1 here
                $QA->setFeatureSet( $featureSet );
                $QA->realignMTSpaces();

                //this should every time be ok because MT preserve tags, but we use the check on the errors
                //for logic correctness
                if ( !$QA->thereAreErrors() ) {

                    // Note: DomDocument class forces the conversion of some entities like &#10; to the original character "\n"
                    $match[ 'translation' ] = $QA->getTrgNormalized();

                } else {
                    $this->_doLog( $QA->getErrors() );
                }

            }

            $user = new UserStruct();

            if ( !$contributionStruct->getUser()->isAnonymous() ) {
                $user = $contributionStruct->getUser();
            }

            $match[ 'created_by' ] = Utils::changeMemorySuggestionSource(
                    $match,
                    $contributionStruct->getJobStruct()->tm_keys,
                    $user->uid
            );

            $match = $this->_matchRewrite( $match );

            if ( $contributionStruct->concordanceSearch ) {

                $regularExpressions = $this->tokenizeSourceSearch( $contributionStruct->getContexts()->segment );

                if ( !$contributionStruct->fromTarget ) {
                    [ $match[ 'segment' ], $match[ 'translation' ] ] = $this->_formatConcordanceValues( $match[ 'segment' ], $match[ 'translation' ], $regularExpressions );
                } else {
                    [ $match[ 'translation' ], $match[ 'segment' ] ] = $this->_formatConcordanceValues( $match[ 'segment' ], $match[ 'translation' ], $regularExpressions );
                }

            }

        }

    }

    private function _formatConcordanceValues( $_source, $_target, $regularExpressions ): array {

        $_source = strip_tags( html_entity_decode( $_source ) );
        $_source = preg_replace( '#\x{20}{2,}#u', chr( 0x20 ), $_source );

        //Do something with &$match, tokenize strings and send to client
        $_source = preg_replace( array_keys( $regularExpressions ), array_values( $regularExpressions ), $_source );
        $_target = strip_tags( html_entity_decode( $_target ) );

        return [ $_source, $_target ];

    }

    /**
     * @param array $match
     *
     * @return array
     */
    protected function _matchRewrite( array $match ): array {

        if ( !empty( $match[ 'score' ] ) && $match[ 'score' ] >= 0.9 ) {
            $match[ 'match' ] = 'ICE_MT';
        }

        return $match;

    }

    /**
     * Build tokens to mark with highlight placeholders
     * the source RESULTS occurrences (correspondences) with text search incoming from ajax
     *
     * @param $text string
     *
     * @return array[string => string] $regularExpressions Pattern is in the key and replacement in the value of the array
     *
     */
    protected function tokenizeSourceSearch( string $text ): array {

        $text = strip_tags( html_entity_decode( $text ) );

        /**
         * remove most punctuation symbols
         *
         * \x{84} => „
         * \x{82} => ‚ //single low quotation mark
         * \x{91} => ‘
         * \x{92} => ’
         * \x{93} => “
         * \x{94} => ”
         * \x{B7} => · //Middle dot - Georgian comma
         * \x{AB} => «
         * \x{BB} => »
         */
        $tmp_text = preg_replace( '#[\x{BB}\x{AB}\x{B7}\x{84}\x{82}\x{91}\x{92}\x{93}\x{94}.(){}\[\];:,\"\'\#+*]+#u', chr( 0x20 ), $text );
        $tmp_text = str_replace( ' - ', chr( 0x20 ), $tmp_text );
        $tmp_text = preg_replace( '#\x{20}{2,}#u', chr( 0x20 ), $tmp_text );

        $tokenizedBySpaces  = explode( " ", $tmp_text );
        $regularExpressions = [];
        foreach ( $tokenizedBySpaces as $token ) {
            $token = trim( $token );
            if ( $token != '' ) {
                $regularExp                        = '|(\s{1})?' . addslashes( $token ) . '(\s{1})?|ui'; /* unicode insensitive */
                $regularExpressions[ $regularExp ] = '$1#{' . $token . '}#$2'; /* unicode insensitive */
            }
        }

        //sort by the len of the Keys ( regular expressions ) in desc ordering
        /*
         *

            Normal Ordering:
            array(
                '|(\s{1})?a(\s{1})?|ui'         => '$1#{a}#$2',
                '|(\s{1})?beautiful(\s{1})?|ui' => '$1#{beautiful}#$2',
            );
            Obtained Result:
            preg_replace result => Be#{a}#utiful //WRONG

            With reverse ordering:
            array(
                '|(\s{1})?beautiful(\s{1})?|ui' => '$1#{beautiful}#$2',
                '|(\s{1})?a(\s{1})?|ui'         => '$1#{a}$2#',
            );
            Obtained Result:
            preg_replace result => #{be#{a}#utiful}#

         */
        uksort( $regularExpressions, [ 'self', '_sortByLenDesc' ] );

        return $regularExpressions;
    }

    /**
     * @param GetContributionRequest $contributionStruct
     * @param JobStruct              $jobStruct
     * @param string                 $targetLang
     * @param FeatureSet             $featureSet
     * @param bool                   $isCrossLang
     *
     * @return array
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws Exception
     */
    protected function _getMatches( GetContributionRequest $contributionStruct, JobStruct $jobStruct, string $targetLang, FeatureSet $featureSet, bool $isCrossLang = false ): array {

        $_config              = [];
        $_config[ 'segment' ] = $contributionStruct->getContexts()->segment;
        $_config[ 'source' ]  = $jobStruct->source;
        $_config[ 'target' ]  = $targetLang;
        $_config[ 'uid' ]     = $contributionStruct->getUser()->uid ?? 0;

        $_config[ 'email' ] = AppConfig::$MYMEMORY_API_KEY;

        $_config[ 'context_before' ] = $contributionStruct->getContexts()->context_before;
        $_config[ 'context_after' ]  = $contributionStruct->getContexts()->context_after;
        $_config[ 'id_user' ]        = $this->_extractAvailableKeysForUser( $contributionStruct );
        $_config[ 'num_result' ]     = $contributionStruct->resultNum;
        $_config[ 'isConcordance' ]  = $contributionStruct->concordanceSearch;

        $_config[ 'dialect_strict' ]                   = $contributionStruct->dialect_strict;
        $_config[ 'priority_key' ]                     = $contributionStruct->tm_prioritization;
        $_config[ MetadataDao::SUBFILTERING_HANDLERS ] = $contributionStruct->subfiltering_handlers;

        // penalty_key
        $penalty_key = TmKeyManager::getPenaltyMap( $contributionStruct->getJobStruct()->tm_keys, 'r', 'tm', $contributionStruct->getUser()->uid, $contributionStruct->userRole );
        if ( !empty( $penalty_key ) ) {
            $_config[ 'penalty_key' ] = $penalty_key;
        }

        if ( !empty( $contributionStruct->public_tm_penalty ) ) {
            $_config[ 'public_tm_penalty' ] = $contributionStruct->public_tm_penalty;
        }

        if ( $contributionStruct->concordanceSearch && $contributionStruct->fromTarget ) {
            //invert direction
            $_config[ 'target' ] = $jobStruct->source;
            $_config[ 'source' ] = $targetLang;
        }

        if ( $jobStruct->id_tms == 1 ) {

            /**
             * Match Enabled
             */

            $_config[ 'get_mt' ]  = true;
            $_config[ 'mt_only' ] = false;
            if ( $jobStruct->id_mt_engine != 1 ) {
                /**
                 * Don't get MT contribution from Match ( Custom MT )
                 */
                $_config[ 'get_mt' ] = false;
            }

            if ( $jobStruct->only_private_tm ) {
                $_config[ 'onlyprivate' ] = true;
            }

            $_TMS = true; /* Match */

        } else {
            if ( $jobStruct->id_tms == 0 && $jobStruct->id_mt_engine == 1 ) {

                /**
                 * Match disabled but MT Enabled, and it is NOT a Custom one
                 * So tell to Match to get MT only
                 */
                $_config[ 'get_mt' ]  = true;
                $_config[ 'mt_only' ] = true;

                $_TMS = true; /* Match */

            }
        }

        if ( $isCrossLang ) {
            $_config[ 'get_mt' ] = false;
        }

        /**
         * if No TM server and No MT selected $_TMS is not defined,
         * so we want not to perform TMS Call
         * This calls the TMEngine to get memories
         */
        $tms_match = [];

        if ( isset( $_TMS ) ) {

            $tmEngine = $contributionStruct->getTMEngine( $featureSet );
            $config   = array_merge( $tmEngine->getConfigStruct(), $_config );

            $temp_matches = [];

            if ( $this->issetSourceAndTarget( $config ) ) {
                $tmEngine->setMTPenalty( $contributionStruct->mt_quality_value_in_editor ? 100 - $contributionStruct->mt_quality_value_in_editor : null ); // can be (100-102 == -2). In AbstractEngine it will be set as (100 - -2 == 102)
                $temp_matches = $tmEngine->get( $config );
            }

            if ( !empty( $temp_matches ) ) {

                $dataRefMap = $contributionStruct->dataRefMap ?: [];
                /** @var GetMemoryResponse $temp_matches */
                $tms_match = $temp_matches->get_matches_as_array( 1 );
            }
        }

        $mt_result = [];

        if (
                $jobStruct->id_mt_engine > 1 /* Get MT Directly */ &&
                !$contributionStruct->concordanceSearch &&
                !$isCrossLang
        ) {

            if ( ( $contributionStruct->mt_quality_value_in_editor ?? 0 ) > 99 || empty( $tms_match ) || (int)str_replace( "%", "", $tms_match[ 0 ][ 'match' ] ) < 100 ) {

                /**
                 * Call The MT EnginesFactory IF
                 * - The user has set an MT Quality value in the editor > 99
                 * OR
                 * - The TM EnginesFactory has not returned any match
                 * OR
                 * - The TM EnginesFactory has returned a match with a score < 100
                 */
                $mt_engine = $contributionStruct->getMTEngine( $featureSet );
                $config    = $mt_engine->getConfigStruct();

                //if a callback is not set only the first argument is returned, get the config params from the callback
                $config = $featureSet->filter( 'beforeGetContribution', $config, $mt_engine, $jobStruct ); //MMT

                $config[ 'pid' ]                 = $jobStruct->id_project;
                $config[ 'segment' ]             = $contributionStruct->getContexts()->segment;
                $config[ 'source' ]              = $jobStruct->source;
                $config[ 'target' ]              = $jobStruct->target;
                $config[ 'email' ]               = AppConfig::$MYMEMORY_API_KEY;
                $config[ 'segid' ]               = $contributionStruct->segmentId;
                $config[ 'job_id' ]              = $jobStruct->id;
                $config[ 'job_password' ]        = $jobStruct->password;
                $config[ 'session' ]             = $contributionStruct->getSessionId();
                $config[ 'all_job_tm_keys' ]     = $jobStruct->tm_keys;
                $config[ 'project_id' ]          = $contributionStruct->getProjectStruct()->id;
                $config[ 'context_list_before' ] = $contributionStruct->context_list_before;
                $config[ 'context_list_after' ]  = $contributionStruct->context_list_after;
                $config[ 'user_id' ]             = $contributionStruct->getUser()->uid;
                $config[ 'tuid' ]                = $jobStruct->id . ":" . $contributionStruct->segmentId;

                if ( $contributionStruct->mt_evaluation ) {
                    $config[ 'include_score' ] = $contributionStruct->mt_evaluation;
                }

                if ( $contributionStruct->mt_qe_workflow_enabled ) {
                    // Initialize the MTQEWorkflowParams object with the workflow parameters from the queue element.
                    $mt_qe_config                = new MTQEWorkflowParams( json_decode( $queueElement->params->mt_qe_workflow_parameters ?? null, true ) ?? [] ); // params or default configuration (NULL safe)
                    $config[ 'mt_qe_engine_id' ] = $mt_qe_config->qe_model_version;
                }

                $mt_engine->setMTPenalty( $contributionStruct->mt_quality_value_in_editor ? 100 - $contributionStruct->mt_quality_value_in_editor : null ); // can be (100-102 == -2). In AbstractEngine it will be set as (100 - -2 == 102)

                $mt_result = $mt_engine->get( $config );
            }
        }

        return [ $mt_result, $tms_match ];
    }

    /**
     * @param $_config
     *
     * @return bool
     */
    private function issetSourceAndTarget( $_config ): bool {
        return ( isset( $_config[ 'source' ] ) and $_config[ 'source' ] !== '' and isset( $_config[ 'target' ] ) and $_config[ 'target' ] !== '' );
    }

    private function _sortByLenDesc( $stringA, $stringB ): int {
        if ( strlen( $stringA ) == strlen( $stringB ) ) {
            return 0;
        }

        return ( strlen( $stringB ) < strlen( $stringA ) ) ? -1 : 1;
    }

    /**
     * @param array                  $matches
     * @param GetContributionRequest $contributionStruct
     *
     * @throws ReflectionException
     * @throws Exception
     */
    private function updateAnalysisSuggestion( array $matches, GetContributionRequest $contributionStruct ) {

        if (
                count( $matches ) > 0 and
                $contributionStruct->segmentId !== null and
                $contributionStruct->getJobStruct() !== null and
                !empty( $contributionStruct->getJobStruct()->id )
        ) {

            $segmentTranslation = SegmentTranslationDao::findBySegmentAndJob( $contributionStruct->segmentId, $contributionStruct->getJobStruct()->id );

            // Run updateFirstTimeOpenedContribution ONLY on translations in NEW status
            if ( $segmentTranslation->status === TranslationStatus::STATUS_NEW ) {

                foreach ( $matches as $k => $m ) {

                    // normalize data for saving `suggestions_array`

                    if ( $m[ 'created_by' ] == 'MT!' ) {
                        $matches[ $k ][ 'created_by' ] = EngineConstants::MT; //Match returns MT!
                    } else {
                        $user = new UserStruct();

                        if ( !$contributionStruct->getUser()->isAnonymous() ) {
                            $user = $contributionStruct->getUser();
                        }

                        $matches[ $k ][ 'created_by' ] = Utils::changeMemorySuggestionSource(
                                $m,
                                $contributionStruct->getJobStruct()->tm_keys,
                                $user->uid
                        );
                    }
                }

                $suggestions_json_array = json_encode( $matches );
                $match                  = $matches[ 0 ];

                $data                        = [];
                $data[ 'suggestions_array' ] = $suggestions_json_array;
                $data[ 'suggestion' ]        = $match[ 'raw_translation' ]; // this is Layer 0
                $data[ 'translation' ]       = $match[ 'raw_translation' ]; // this is Layer 0
                $data[ 'suggestion_match' ]  = str_replace( '%', '', $match[ 'match' ] );

                $where = [
                        'id_segment' => $contributionStruct->segmentId,
                        'id_job'     => $contributionStruct->getJobStruct()->id,
                        'status'     => TranslationStatus::STATUS_NEW
                ];

                SegmentTranslationDao::updateFirstTimeOpenedContribution( $data, $where );
            }
        }
    }
}