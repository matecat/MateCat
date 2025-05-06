<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/05/16
 * Time: 20.36
 *
 */

namespace AsyncTasks\Workers;

use Constants\Ices;
use Constants_Engines;
use Constants_TranslationStatus;
use Contribution\ContributionRequestStruct;
use Exception;
use FeatureSet;
use INIT;
use Jobs_JobStruct;
use Matecat\SubFiltering\AbstractFilter;
use Matecat\SubFiltering\MateCatFilter;
use Model\Analysis\Constants\InternalMatchesConstants;
use MTQE\Templates\DTO\MTQEWorkflowParams;
use PostProcess;
use Stomp\Exception\StompException;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Commons\QueueElement;
use TaskRunner\Exceptions\EndQueueException;
use TaskRunner\Exceptions\ReQueueException;
use TmKeyManagement_TmKeyManagement;
use Translations_SegmentTranslationDao;
use Users_UserStruct;
use Utils;

class GetContributionWorker extends AbstractWorker {

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

        $contributionStruct = new ContributionRequestStruct( $queueElement->params->toArray() );

        $this->_checkDatabaseConnection();

        $this->_execGetContribution( $contributionStruct );

    }

    /**
     * @param                $matches
     * @param AbstractFilter $filter
     *
     * @return array
     * @throws Exception
     */
    private function normalizeMatchesToLayer0( $matches, AbstractFilter $filter ) {
        foreach ( $matches as $k => $m ) {
            $matches[ $k ][ 'raw_segment' ]     = $filter->fromLayer2ToLayer0( $matches[ $k ][ 'raw_segment' ] );
            $matches[ $k ][ 'segment' ]         = $filter->fromLayer2ToLayer0( html_entity_decode( $matches[ $k ][ 'segment' ] ) );
            $matches[ $k ][ 'translation' ]     = $filter->fromLayer2ToLayer0( html_entity_decode( $matches[ $k ][ 'translation' ] ) );
            $matches[ $k ][ 'raw_translation' ] = $filter->fromLayer2ToLayer0( $matches[ $k ][ 'raw_translation' ] );
        }

        return $matches;
    }

    /**
     * @param ContributionRequestStruct $contributionStruct
     *
     * @throws Exception
     */
    protected function _execGetContribution( ContributionRequestStruct $contributionStruct ) {

        $jobStruct = $contributionStruct->getJobStruct();

        $featureSet = new FeatureSet();
        $featureSet->loadForProject( $contributionStruct->getProjectStruct() );

        [ $mt_result, $matches ] = $this->_getMatches( $contributionStruct, $jobStruct, $jobStruct->target, $featureSet );

        $matches = $this->_sortMatches( $mt_result, $matches );

        if ( !$contributionStruct->concordanceSearch ) {
            //execute these lines only in segment contribution search,
            //in case of user concordance search skip these lines
            $this->updateAnalysisSuggestion( $matches, $contributionStruct, $featureSet );
        }

        $matches = array_slice( $matches, 0, $contributionStruct->resultNum );
        $this->normalizeTMMatches( $matches, $contributionStruct, $featureSet, $jobStruct->target );

        $this->_publishPayload( $matches, $contributionStruct );

        // cross language matches
        if ( !empty( $contributionStruct->crossLangTargets ) ) {
            $crossLangMatches = [];

            foreach ( $contributionStruct->crossLangTargets as $lang ) {

                // double check for not black lang
                if ( $lang !== '' ) {
                    [ , $matches ] = $this->_getMatches( $contributionStruct, $jobStruct, $lang, $featureSet, true );

                    $matches = array_slice( $matches, 0, $contributionStruct->resultNum );
                    $this->normalizeTMMatches( $matches, $contributionStruct, $featureSet, $lang );

                    foreach ( $matches as $match ) {
                        $crossLangMatches[] = $match;
                    }
                }
            }

            if ( !empty( $crossLangMatches ) ) {
                usort( $crossLangMatches, [ "self", "__compareScore" ] );
                $crossLangMatches = array_reverse( $crossLangMatches );
            }

            if ( false === $contributionStruct->concordanceSearch ) {
                $this->_publishPayload( $crossLangMatches, $contributionStruct, true );
            }
        }
    }

    /**
     * Suggestion array must be updated every time we receive the matches
     *
     * @param $id_segment
     * @param $matches
     */
    protected function _updateSuggestionArray( $id_segment, $matches ) {
        Translations_SegmentTranslationDao::updateSuggestionsArray( $id_segment, $matches );
    }

    /**
     * @param array                     $content
     * @param ContributionRequestStruct $contributionStruct
     *
     * @param bool                      $isCrossLang
     *
     * @throws StompException
     */
    protected function _publishPayload( array $content, ContributionRequestStruct $contributionStruct, $isCrossLang = false ) {

        $type = 'contribution';

        if ( $contributionStruct->concordanceSearch ) {
            $type = 'concordance';
        }

        if ( $isCrossLang ) {
            $type = 'cross_language_matches';
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
    protected function _extractAvailableKeysForUser( ContributionRequestStruct $contributionStruct ) {

        //find all the job's TMs with write grants and make a contribution to them
        $tm_keys = TmKeyManagement_TmKeyManagement::getJobTmKeys( $contributionStruct->getJobStruct()->tm_keys, 'r', 'tm', $contributionStruct->getUser()->uid, $contributionStruct->userRole );

        $keyList = [];
        if ( !empty( $tm_keys ) ) {
            foreach ( $tm_keys as $tm_info ) {
                $keyList[] = $tm_info->key;
            }
        }

        return $keyList;

    }

    private static function __compareScore( $a, $b ) {
        if ( floatval( $a[ 'match' ] ) == floatval( $b[ 'match' ] ) ) {
            return 0;
        }

        return ( floatval( $a[ 'match' ] ) < floatval( $b[ 'match' ] ) ? -1 : 1 );
    }

    /**
     * @param array                     $matches
     * @param ContributionRequestStruct $contributionStruct
     * @param FeatureSet                $featureSet
     *
     * @param                           $targetLang
     *
     * @throws Exception
     */
    public function normalizeTMMatches( array &$matches, ContributionRequestStruct $contributionStruct, FeatureSet $featureSet, $targetLang ) {

        $Filter = MateCatFilter::getInstance(
                $featureSet,
                $contributionStruct->getJobStruct()->source,
                $targetLang,
                $contributionStruct->dataRefMap
        );

        foreach ( $matches as &$match ) {

            if ( strpos( $match[ 'created_by' ], Constants_Engines::MT ) !== false ) {

                $match[ 'match' ] = Constants_Engines::MT;

                $QA = new PostProcess( $match[ 'raw_segment' ], $match[ 'raw_translation' ] );
                $QA->setFeatureSet( $featureSet );
                $QA->realignMTSpaces();

                //this should every time be ok because MT preserve tags, but we use the check on the errors
                //for logic correctness
                if ( !$QA->thereAreErrors() ) {
                    $match[ 'raw_translation' ] = $QA->getTrgNormalized();                                    // DomDocument class forces the conversion of some entities like &#10;
                    $match[ 'raw_translation' ] = $Filter->fromLayer2ToLayer1( $match[ 'raw_translation' ] );   // Convert \n to decimal entity &#10;
                    $match[ 'translation' ]     = $Filter->fromLayer1ToLayer2( $match[ 'raw_translation' ] ); // Convert &#10; to layer2 placeholder for the UI
                } else {
                    $this->_doLog( $QA->getErrors() );
                }

            }

            if ( $match[ 'created_by' ] == 'MT!' ) {

                $match[ 'created_by' ] = Constants_Engines::MT; //MyMemory returns MT!

            } elseif ( $match[ 'created_by' ] == 'NeuralMT' ) {

                $match[ 'created_by' ] = Constants_Engines::MT; //For now do not show differences

            } else {

                $user = new Users_UserStruct();

                if ( !$contributionStruct->getUser()->isAnonymous() ) {
                    $user = $contributionStruct->getUser();
                }

                $match[ 'created_by' ] = Utils::changeMemorySuggestionSource(
                        $match,
                        $contributionStruct->getJobStruct()->tm_keys,
                        $user->uid
                );
            }

            $match = $this->_matchRewrite( $match, $contributionStruct, $featureSet );

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

    private function _formatConcordanceValues( $_source, $_target, $regularExpressions ) {

        $_source = strip_tags( html_entity_decode( $_source ) );
        $_source = preg_replace( '#\x{20}{2,}#u', chr( 0x20 ), $_source );

        //Do something with &$match, tokenize strings and send to client
        $_source = preg_replace( array_keys( $regularExpressions ), array_values( $regularExpressions ), $_source );
        $_target = strip_tags( html_entity_decode( $_target ) );

        return [ $_source, $_target ];

    }

    /**
     * @param array                     $match
     * @param ContributionRequestStruct $contributionStruct
     * @param FeatureSet                $featureSet
     *
     * @return array
     * @throws Exception
     */
    protected function _matchRewrite( array $match, ContributionRequestStruct $contributionStruct, FeatureSet $featureSet ) {

        //Rewrite ICE matches as 101%
        if ( $match[ 'match' ] == '100%' ) {
            [ $lang, ] = explode( '-', $contributionStruct->getJobStruct()->target );
            if ( isset( $match[ 'ICE' ] ) && $match[ 'ICE' ] && !in_array( $lang, ICES::$iceLockDisabledForTargetLangs ) ) {
                $match[ 'match' ] = '101%';
            }
            //else do not rewrite the match value
        }

        if ( !empty( $match[ 'score' ] ) && $match[ 'score' ] >= 0.9 ) {
            $match[ 'match' ] = 'ICE_MT';
        }

        return $match;

    }

    /**
     * Build tokens to mark with highlight placeholders
     * the source RESULTS occurrences ( correspondences ) with text search incoming from ajax
     *
     * @param $text string
     *
     * @return array[string => string] $regularExpressions Pattern is in the key and replacement in the value of the array
     *
     */
    protected function tokenizeSourceSearch( $text ) {

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
     * @param ContributionRequestStruct $contributionStruct
     * @param Jobs_JobStruct            $jobStruct
     * @param string                    $targetLang
     * @param FeatureSet                $featureSet
     * @param bool                      $isCrossLang
     *
     * @return array
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws Exception
     */
    protected function _getMatches( ContributionRequestStruct $contributionStruct, Jobs_JobStruct $jobStruct, string $targetLang, FeatureSet $featureSet, bool $isCrossLang = false ): array {

        $_config              = [];
        $_config[ 'segment' ] = $contributionStruct->getContexts()->segment;
        $_config[ 'source' ]  = $jobStruct->source;
        $_config[ 'target' ]  = $targetLang;
        $_config[ 'uid' ]     = $contributionStruct->getUser()->uid ?? 0;

        $_config[ 'email' ] = INIT::$MYMEMORY_API_KEY;

        $_config[ 'context_before' ] = $contributionStruct->getContexts()->context_before;
        $_config[ 'context_after' ]  = $contributionStruct->getContexts()->context_after;
        $_config[ 'id_user' ]        = $this->_extractAvailableKeysForUser( $contributionStruct );
        $_config[ 'num_result' ]     = $contributionStruct->resultNum;
        $_config[ 'isConcordance' ]  = $contributionStruct->concordanceSearch;

        if ( $contributionStruct->dialect_strict !== null ) {
            $_config[ 'dialect_strict' ] = $contributionStruct->dialect_strict;
        }

        if ( $contributionStruct->tm_prioritization !== null ) {
            $_config[ 'priority_key' ] = $contributionStruct->tm_prioritization;
        }

        if ( !empty( $contributionStruct->penalty_key ) ) {
            $_config[ 'penalty_key' ] = $contributionStruct->penalty_key;
        }

        if ( $contributionStruct->concordanceSearch && $contributionStruct->fromTarget ) {
            //invert direction
            $_config[ 'target' ] = $jobStruct->source;
            $_config[ 'source' ] = $targetLang;
        }

        if ( $jobStruct->id_tms == 1 ) {

            /**
             * MyMemory Enabled
             */

            $_config[ 'get_mt' ]  = true;
            $_config[ 'mt_only' ] = false;
            if ( $jobStruct->id_mt_engine != 1 ) {
                /**
                 * Don't get MT contribution from MyMemory ( Custom MT )
                 */
                $_config[ 'get_mt' ] = false;
            }

            if ( $jobStruct->only_private_tm ) {
                $_config[ 'onlyprivate' ] = true;
            }

            $_TMS = true; /* MyMemory */

        } else {
            if ( $jobStruct->id_tms == 0 && $jobStruct->id_mt_engine == 1 ) {

                /**
                 * MyMemory disabled but MT Enabled, and it is NOT a Custom one
                 * So tell to MyMemory to get MT only
                 */
                $_config[ 'get_mt' ]  = true;
                $_config[ 'mt_only' ] = true;

                $_TMS = true; /* MyMemory */

            }
        }

        if ( $isCrossLang ) {
            $_config[ 'get_mt' ] = false;
        }

        $mt_qe_configuration = new MTQEWorkflowParams( json_decode( $contributionStruct->mt_qe_config, true ) );

        /**
         * if No TM server and No MT selected $_TMS is not defined,
         * so we want not to perform TMS Call
         */
        /**
         *
         * This calls the TMEngine to get memories
         */
        if ( isset( $_TMS ) ) {

            $tmEngine = $contributionStruct->getTMEngine( $featureSet );
            $config   = array_merge( $tmEngine->getConfigStruct(), $_config );

            $temp_matches = [];

            if ( $this->issetSourceAndTarget( $config ) ) {
                $temp_matches = $tmEngine->get( $config );
            }

            if ( !empty( $temp_matches ) ) {

                $dataRefMap = $contributionStruct->dataRefMap ?: [];
                $tms_match  = $this->__filterTMMatches( $temp_matches->get_matches_as_array( 2, $dataRefMap, $_config[ 'source' ], $_config[ 'target' ] ), $contributionStruct->mt_qe_workflow_enabled, $mt_qe_configuration );
            }
        }

        $mt_result = [];

        if (
                $jobStruct->id_mt_engine > 1 /* Request MT Directly */ &&
                !$contributionStruct->concordanceSearch &&
                !$isCrossLang
        ) {

            if ( empty( $tms_match ) || (int)str_replace( "%", "", $tms_match[ 0 ][ 'match' ] ) < 100 ) {

                $mt_engine = $contributionStruct->getMTEngine( $featureSet );
                $config    = $mt_engine->getConfigStruct();

                //if a callback is not set only the first argument is returned, get the config params from the callback
                $config = $featureSet->filter( 'beforeGetContribution', $config, $mt_engine, $jobStruct );

                $config[ 'pid' ]                 = $jobStruct->id_project;
                $config[ 'segment' ]             = $contributionStruct->getContexts()->segment;
                $config[ 'source' ]              = $jobStruct->source;
                $config[ 'target' ]              = $jobStruct->target;
                $config[ 'email' ]               = INIT::$MYMEMORY_API_KEY;
                $config[ 'segid' ]               = $contributionStruct->segmentId;
                $config[ 'job_id' ]              = $jobStruct->id;
                $config[ 'job_password' ]        = $jobStruct->password;
                $config[ 'session' ]             = $contributionStruct->getSessionId();
                $config[ 'all_job_tm_keys' ]     = $jobStruct->tm_keys;
                $config[ 'project_id' ]          = $contributionStruct->getProjectStruct()->id;
                $config[ 'context_list_before' ] = $contributionStruct->context_list_before;
                $config[ 'context_list_after' ]  = $contributionStruct->context_list_after;
                $config[ 'user_id' ]             = $contributionStruct->getUser()->uid;

                if ( $contributionStruct->mt_evaluation ) {
                    $config[ 'include_score' ]   = $contributionStruct->mt_evaluation;
                    $config[ 'mt_qe_engine_id' ] = $mt_qe_configuration->qe_model_type;
                }

                $mt_result = $mt_engine->get( $config );
            }
        }

        $matches = [];
        if ( !empty( $tms_match ) ) {
            $matches = $tms_match;
        }

        return [ $mt_result, $matches ];
    }

    /**
     * Filters Translation Memory (TM) matches based on specific criteria defined in the MTQE workflow parameters.
     *
     * @param array              $matches An array of TM matches to be filtered.
     * @param bool               $mt_qe_workflow_enabled
     * @param MTQEWorkflowParams $mt_qe_config
     *
     * @return array The filtered array of TM matches.
     */
    private function __filterTMMatches( array $matches, bool $mt_qe_workflow_enabled, MTQEWorkflowParams $mt_qe_config ): array {

        // Filter the matches array using a callback function.
        return array_filter( $matches, function ( $match ) use ( $mt_qe_config, $mt_qe_workflow_enabled ) {

            // Check if the MTQE workflow is enabled.
            if ( $mt_qe_workflow_enabled ) {

                // If the "analysis_ignore_101" flag is set, ignore all matches.
                if ( $mt_qe_config->ignore_101 ) {
                    return false;
                }

                // If the "analysis_ignore_100" flag is set, ignore matches with a score <= 100 unless they are ICE matches.
                if ( $mt_qe_config->ignore_100 ) {
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
     * @param $_config
     *
     * @return bool
     */
    private function issetSourceAndTarget( $_config ) {
        return ( isset( $_config[ 'source' ] ) and $_config[ 'source' ] !== '' and isset( $_config[ 'target' ] ) and $_config[ 'target' ] !== '' );
    }

    /**
     * @param $mt_result
     * @param $matches
     *
     * @return array
     */
    protected function _sortMatches( $mt_result, $matches ) {
        if ( !empty( $mt_result ) ) {
            $matches[] = $mt_result;
            usort( $matches, [ "self", "__compareScore" ] );
            //this is necessary since usort sorts is ascending order, thus inverting the ranking
            $matches = array_reverse( $matches );
        }

        return $matches;
    }

    private function _sortByLenDesc( $stringA, $stringB ) {
        if ( strlen( $stringA ) == strlen( $stringB ) ) {
            return 0;
        }

        return ( strlen( $stringB ) < strlen( $stringA ) ) ? -1 : 1;
    }

    /**
     * @param                           $matches
     * @param ContributionRequestStruct $contributionStruct
     * @param FeatureSet                $featureSet
     *
     * @throws Exception
     */
    private function updateAnalysisSuggestion( $matches, ContributionRequestStruct $contributionStruct, FeatureSet $featureSet ) {

        if (
                is_array( $matches ) and
                count( $matches ) > 0 and
                $contributionStruct->segmentId !== null and
                $contributionStruct->getJobStruct() !== null and
                !empty( $contributionStruct->getJobStruct()->id )
        ) {

            $segmentTranslation = Translations_SegmentTranslationDao::findBySegmentAndJob( $contributionStruct->segmentId, $contributionStruct->getJobStruct()->id );

            // Run updateFirstTimeOpenedContribution ONLY on translations in NEW status
            if ( $segmentTranslation->status === Constants_TranslationStatus::STATUS_NEW ) {

                $Filter = MateCatFilter::getInstance( $featureSet, $contributionStruct->getJobStruct()->source, $contributionStruct->getJobStruct()->target );

                foreach ( $matches as $k => $m ) {

                    // normalize data for saving `suggestions_array`
                    $matches[ $k ][ 'raw_segment' ]     = $Filter->fromLayer1ToLayer0( $matches[ $k ][ 'raw_segment' ] );
                    $matches[ $k ][ 'segment' ]         = $Filter->fromLayer1ToLayer0( html_entity_decode( $matches[ $k ][ 'segment' ] ) );
                    $matches[ $k ][ 'translation' ]     = $Filter->fromLayer1ToLayer0( html_entity_decode( $matches[ $k ][ 'translation' ] ) );
                    $matches[ $k ][ 'raw_translation' ] = $Filter->fromLayer1ToLayer0( $matches[ $k ][ 'raw_translation' ] );

                    if ( $matches[ $k ][ 'created_by' ] == 'MT!' ) {
                        $matches[ $k ][ 'created_by' ] = Constants_Engines::MT; //MyMemory returns MT!
                    } else {
                        $user = new Users_UserStruct();

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
                $data[ 'suggestion' ]        = $match[ 'raw_translation' ];
                $data[ 'translation' ]       = $match[ 'raw_translation' ];
                $data[ 'suggestion_match' ]  = str_replace( '%', '', $match[ 'match' ] );

                $where = [
                        'id_segment' => $contributionStruct->segmentId,
                        'id_job'     => $contributionStruct->getJobStruct()->id,
                        'status'     => Constants_TranslationStatus::STATUS_NEW
                ];

                Translations_SegmentTranslationDao::updateFirstTimeOpenedContribution( $data, $where );
            }
        }
    }
}