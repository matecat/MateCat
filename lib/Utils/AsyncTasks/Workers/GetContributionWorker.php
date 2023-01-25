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
use Constants_TranslationStatus;
use Contribution\ContributionRequestStruct;
use FeatureSet;
use INIT;
use PostProcess;
use Stomp;
use Matecat\SubFiltering\MateCatFilter;
use TaskRunner\Commons\AbstractElement;
use TaskRunner\Commons\AbstractWorker;
use TaskRunner\Commons\QueueElement;
use TaskRunner\Exceptions\EndQueueException;
use TaskRunner\Exceptions\ReQueueException;
use TmKeyManagement_TmKeyManagement;
use Utils;

class GetContributionWorker extends AbstractWorker {

    /**
     * @param AbstractElement $queueElement
     *
     * @return null
     * @throws EndQueueException
     * @throws ReQueueException
     * @throws \Exception
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
     * @param ContributionRequestStruct $contributionStruct
     *
     * @throws ReQueueException
     * @throws \Exception
     */
    protected function _execGetContribution( ContributionRequestStruct $contributionStruct ) {

        $jobStruct = $contributionStruct->getJobStruct();

        $featureSet = new FeatureSet();
        $featureSet->loadForProject( $contributionStruct->getProjectStruct() );

        list( $mt_result, $matches ) = $this->_getMatches( $contributionStruct, $jobStruct, $jobStruct->target, $featureSet );

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
                if( $lang !== '' ){
                    list( $mt_result, $matches ) = $this->_getMatches( $contributionStruct, $jobStruct, $lang, $featureSet, true );

                    $matches = array_slice( $matches, 0, $contributionStruct->resultNum );
                    $this->normalizeTMMatches( $matches, $contributionStruct, $featureSet, $lang );

                    foreach ( $matches as $match ) {
                        $crossLangMatches[] = $match;
                    }
                }
            }

            if( !empty($crossLangMatches) ){
                usort( $crossLangMatches, [ "self", "__compareScore" ] );
                $crossLangMatches = array_reverse( $crossLangMatches );
            }

            if(false === $contributionStruct->concordanceSearch){
                $this->_publishPayload( $crossLangMatches, $contributionStruct, true );
            }
        }
    }

    /**
     * @param array                     $content
     * @param ContributionRequestStruct $contributionStruct
     *
     * @param bool                      $isCrossLang
     *
     * @throws \StompException
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
                                'id_segment' => $contributionStruct->segmentId,
                                'matches'    => $content,
                        ],
                        'id_client' => $contributionStruct->id_client,
                ]
        ];

        $message = json_encode( $_object, true );

        $stomp = new Stomp( INIT::$QUEUE_BROKER_ADDRESS );
        $stomp->connect();
        $stomp->send( INIT::$SSE_NOTIFICATIONS_QUEUE_NAME,
                $message,
                [ 'persistent' => 'false' ]
        );

        $this->_doLog( $message );

    }


    protected function _extractAvailableKeysForUser( ContributionRequestStruct $contributionStruct ) {

        //find all the job's TMs with write grants and make a contribution to them
        $tm_keys = TmKeyManagement_TmKeyManagement::getJobTmKeys( $contributionStruct->getJobStruct()->tm_keys, 'r', 'tm', $contributionStruct->user->uid, $contributionStruct->userRole );

        $keyList = [];
        if ( !empty( $tm_keys ) ) {
            foreach ( $tm_keys as $i => $tm_info ) {
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
     * @throws \Exception
     */
    public function normalizeTMMatches( array &$matches, ContributionRequestStruct $contributionStruct, FeatureSet $featureSet, $targetLang ) {

        $Filter = MateCatFilter::getInstance(
                $featureSet,
                $contributionStruct->getJobStruct()->source,
                $targetLang,
                json_decode($contributionStruct->dataRefMap, true)
        );

        foreach ( $matches as &$match ) {

            if ( strpos( $match[ 'created_by' ], 'MT' ) !== false ) {

                $match[ 'match' ] = 'MT';

                $QA = new PostProcess( $match[ 'raw_segment' ], $match[ 'raw_translation' ] );
                $QA->setFeatureSet( $featureSet );
                $QA->realignMTSpaces();

                //this should every time be ok because MT preserve tags, but we use the check on the errors
                //for logic correctness
                if ( !$QA->thereAreErrors() ) {
                    $match[ 'raw_translation' ] = $QA->getTrgNormalized();                                    // DomDocument class forces the conversion of some entities like &#10;
                    $match[ 'raw_translation' ] = $Filter->fromLayer2ToLayer1($match[ 'raw_translation' ]);   // Convert \n to decimal entity &#10;
                    $match[ 'translation' ]     = $Filter->fromLayer1ToLayer2( $match[ 'raw_translation' ] ); // Convert &#10; to layer2 placeholder for the UI
                } else {
                    $this->_doLog( $QA->getErrors() );
                }

            }

            if ( $match[ 'created_by' ] == 'MT!' ) {

                $match[ 'created_by' ] = 'MT'; //MyMemory returns MT!

            } elseif ( $match[ 'created_by' ] == 'NeuralMT' ) {

                $match[ 'created_by' ] = 'MT'; //For now do not show differences

            } else {

                $user = new \Users_UserStruct();

                if ( !$contributionStruct->getUser()->isAnonymous() ) {
                    $user = $contributionStruct->getUser();
                }

                $match[ 'created_by' ] = Utils::changeMemorySuggestionSource(
                        $match,
                        $contributionStruct->getJobStruct()->tm_keys,
                        $contributionStruct->getJobStruct()->owner,
                        $user->uid
                );
            }

            $match = $this->_matchRewrite( $match, $contributionStruct, $featureSet );

            if ( $contributionStruct->concordanceSearch ) {

                $regularExpressions = $this->tokenizeSourceSearch( $contributionStruct->getContexts()->segment );

                if ( !$contributionStruct->fromTarget ) {
                    list( $match[ 'segment' ], $match[ 'translation' ] ) = $this->_formatConcordanceValues( $match[ 'segment' ], $match[ 'translation' ], $regularExpressions );
                } else {
                    list( $match[ 'translation' ], $match[ 'segment' ] ) = $this->_formatConcordanceValues( $match[ 'segment' ], $match[ 'translation' ], $regularExpressions );
                }

            }

        }

    }

    private function _formatConcordanceValues( $_source, $_target, $regularExpressions ) {

        $_source = strip_tags( html_entity_decode( $_source ) );
        $_source = preg_replace( '#[\x{20}]{2,}#u', chr( 0x20 ), $_source );

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
     * @throws \Exception
     */
    protected function _matchRewrite( array $match, ContributionRequestStruct $contributionStruct, FeatureSet $featureSet ) {

        //Rewrite ICE matches as 101%
        if ( $match[ 'match' ] == '100%' ) {
            list( $lang, ) = explode( '-', $contributionStruct->getJobStruct()->target );
            if ( isset( $match[ 'ICE' ] ) && $match[ 'ICE' ] && array_search( $lang, ICES::$iceLockDisabledForTargetLangs ) === false ) {
                $match[ 'match' ] = '101%';
            }
            //else do not rewrite the match value
        }

        //Allow the plugins to customize matches
        $match = $featureSet->filter( 'matchRewriteForContribution', $match );

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
         * remove most of punctuation symbols
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
        $tmp_text = preg_replace( '#[\x{BB}\x{AB}\x{B7}\x{84}\x{82}\x{91}\x{92}\x{93}\x{94}\.\(\)\{\}\[\];:,\"\'\#\+\*]+#u', chr( 0x20 ), $text );
        $tmp_text = str_replace( ' - ', chr( 0x20 ), $tmp_text );
        $tmp_text = preg_replace( '#[\x{20}]{2,}#u', chr( 0x20 ), $tmp_text );

        $tokenizedBySpaces  = explode( " ", $tmp_text );
        $regularExpressions = [];
        foreach ( $tokenizedBySpaces as $key => $token ) {
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
     * @param                           $jobStruct
     * @param                           $targetLang
     * @param                           $featureSet
     *
     * @param bool                      $isCrossLang
     *
     * @return array
     */
    protected function _getMatches( ContributionRequestStruct $contributionStruct, $jobStruct, $targetLang, $featureSet, $isCrossLang = false ) {

        $_config                 = [];
        $_config[ 'segment' ]    = $contributionStruct->getContexts()->segment;
        $_config[ 'source' ]     = $jobStruct->source;
        $_config[ 'target' ]     = $targetLang;

        $_config[ 'email' ] = INIT::$MYMEMORY_API_KEY;

        $_config[ 'context_before' ] = $contributionStruct->getContexts()->context_before;
        $_config[ 'context_after' ]  = $contributionStruct->getContexts()->context_after;
        $_config[ 'id_user' ]        = $this->_extractAvailableKeysForUser( $contributionStruct );
        $_config[ 'num_result' ]     = $contributionStruct->resultNum;
        $_config[ 'isConcordance' ]  = $contributionStruct->concordanceSearch;

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
                 * MyMemory disabled but MT Enabled and it is NOT a Custom one
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

        /**
         * if No TM server and No MT selected $_TMS is not defined
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

            if($this->issetSourceAndTarget($config)){
                $temp_matches = $tmEngine->get( $config );
            }

            if ( !empty( $temp_matches ) ) {

                $dataRefMap = (isset($contributionStruct->dataRefMap) and $contributionStruct->dataRefMap !== '') ? json_decode($contributionStruct->dataRefMap, true) : [];
                $tms_match = $temp_matches->get_matches_as_array(2, $dataRefMap, $_config[ 'source' ] , $_config[ 'target' ]);
            }
        }

        $mt_result = [];

        if ( $jobStruct->id_mt_engine > 1 /* Request MT Directly */ && !$contributionStruct->concordanceSearch ) {

            if ( empty( $tms_match ) || (int)str_replace( "%", "", $tms_match[ 0 ][ 'match' ] ) < 100 ) {

                /**
                 * @var $mt_engine \Engines_MMT
                 */
                $mt_engine = $contributionStruct->getMTEngine( $featureSet );

                $config = $mt_engine->getConfigStruct();

                //if a callback is not set only the first argument is returned, get the config params from the callback
                $config = $featureSet->filter( 'beforeGetContribution', $config, $mt_engine, $jobStruct );

                $config[ 'segment' ] = $contributionStruct->getContexts()->segment;
                $config[ 'source' ]  = $jobStruct->source;
                $config[ 'target' ]  = $jobStruct->target;
                $config[ 'email' ]   = INIT::$MYMEMORY_API_KEY;
                $config[ 'segid' ]   = $contributionStruct->segmentId;

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
     * @param $_config
     *
     * @return bool
     */
    private function issetSourceAndTarget($_config)
    {
        return (isset($_config[ 'source' ]) and $_config[ 'source' ] !== '' and isset($_config[ 'target' ]) and $_config[ 'target' ] !== '' );
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
     * @throws \Exception
     */
    private function updateAnalysisSuggestion( $matches, ContributionRequestStruct $contributionStruct, FeatureSet $featureSet ) {

        if ( count( $matches ) > 0 ) {

            $Filter = MateCatFilter::getInstance( $featureSet, $contributionStruct->getJobStruct()->source, $contributionStruct->getJobStruct()->target, [] );

            foreach ( $matches as $k => $m ) {

                $matches[ $k ][ 'raw_translation' ] = $Filter->fromLayer1ToLayer0( $matches[ $k ][ 'raw_translation' ] );

                if ( $matches[ $k ][ 'created_by' ] == 'MT!' ) {
                    $matches[ $k ][ 'created_by' ] = 'MT'; //MyMemory returns MT!
                } else {
                    $user = new \Users_UserStruct();

                    if ( !$contributionStruct->getUser()->isAnonymous() ) {
                        $user = $contributionStruct->getUser();
                    }

                    $match[ 'created_by' ] = Utils::changeMemorySuggestionSource(
                            $m,
                            $contributionStruct->getJobStruct()->tm_keys,
                            $contributionStruct->getJobStruct()->owner,
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

            \Translations_SegmentTranslationDao::updateFirstTimeOpenedContribution( $data, $where );


        }

    }

}