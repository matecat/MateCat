<?php

use Constants\Ices;

class getContributionController extends ajaxController {

    protected $id_segment;
    private $concordance_search;
    private $switch_languages;
    private $id_job;
    private $num_results;
    private $text;
    private $source;
    private $target;
    private $id_mt_engine;
    private $id_tms;
    private $id_translator;
    private $password;
    private $tm_keys;

    protected $context_before;
    protected $context_after;

    /**
     * @var Jobs_JobStruct
     */
    private $jobData;

    private $feature_set;

    private $__postInput = array();

    /**
     * @var Projects_ProjectStruct
     */
    private $project;

    public function __construct() {

        parent::__construct();

        $filterArgs = [
                'id_segment'     => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'id_job'         => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'num_results'    => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'text'           => [ 'filter' => FILTER_UNSAFE_RAW ],
                'id_translator'  => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'password'       => [ 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ],
                'is_concordance' => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'from_target'    => [ 'filter' => FILTER_VALIDATE_BOOLEAN ],
                'context_before' => [ 'filter' => FILTER_UNSAFE_RAW ],
                'context_after'  => [ 'filter' => FILTER_UNSAFE_RAW ],
        ];

        $this->__postInput = filter_input_array( INPUT_POST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI
        //$this->__postInput = filter_var_array( $_POST, $filterArgs );

        $this->id_segment         = $this->__postInput[ 'id_segment' ];
        $this->id_job             = $this->__postInput[ 'id_job' ];
        $this->num_results        = $this->__postInput[ 'num_results' ];
        $this->text               = trim( $this->__postInput[ 'text' ] );
        $this->id_translator      = $this->__postInput[ 'id_translator' ];
        $this->concordance_search = $this->__postInput[ 'is_concordance' ];
        $this->switch_languages   = $this->__postInput[ 'from_target' ];
        $this->password           = $this->__postInput[ 'password' ];

        if ( $this->id_translator == 'unknown_translator' ) {
            $this->id_translator = "";
        }

        $this->feature_set = new FeatureSet();

    }

    public function doAction() {

        if ( !$this->concordance_search ) {
            //execute these lines only in segment contribution search,
            //in case of user concordance search skip these lines
            //because segment can be optional
            if ( empty( $this->id_segment ) ) {
                $this->result[ 'errors' ][ ] = array( "code" => -1, "message" => "missing id_segment" );
            }
        }

        if ( is_null( $this->text ) || $this->text === '' ) {
            $this->result[ 'errors' ][ ] = array( "code" => -2, "message" => "missing text" );
        }

        if ( empty( $this->id_job ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -3, "message" => "missing id_job" );
        }

        if ( empty( $this->num_results ) ) {
            $this->num_results = INIT::$DEFAULT_NUM_RESULTS_FROM_TM;
        }

        if ( !empty( $this->result[ 'errors' ] ) ) {
            return -1;
        }

        //get Job Info, we need only a row of jobs ( split )
        $this->jobData = Jobs_JobDao::getByIdAndPassword( $this->id_job, $this->password );

        $pCheck = new AjaxPasswordCheck();
        //check for Password correctness
        if ( empty( $this->jobData ) || !$pCheck->grantJobAccessByJobData( $this->jobData, $this->password ) ) {
            $this->result[ 'errors' ][ ] = array( "code" => -10, "message" => "wrong password" );

            return -1;
        }

        $this->project = Projects_ProjectDao::findById( $this->jobData->id_project );
        $this->feature_set->loadForProject( $this->project );

        /*
         * string manipulation strategy
         *
         */
        if ( !$this->concordance_search ) {
            //
            $this->text           = CatUtils::view2rawxliff( $this->text );
            $this->context_before = CatUtils::view2rawxliff( $this->__postInput[ 'context_before' ] );
            $this->context_after  = CatUtils::view2rawxliff( $this->__postInput[ 'context_after' ] );

            $this->source         = $this->jobData[ 'source' ];
            $this->target         = $this->jobData[ 'target' ];
        } else {

            $regularExpressions = $this->tokenizeSourceSearch();

            if ( $this->switch_languages ) {
                /*
                 *
                 * switch languages from user concordances search on the target language value
                 * Example:
                 * Job is in
                 *      source: it_IT,
                 *      target: de_DE
                 *
                 * user perform a right click for concordance help on a german word or phrase
                 * we want result in italian from german source
                 *
                 */
                $this->source = $this->jobData[ 'target' ];
                $this->target = $this->jobData[ 'source' ];
            } else {
                $this->source = $this->jobData[ 'source' ];
                $this->target = $this->jobData[ 'target' ];
            }
        }

        $this->id_mt_engine = $this->jobData[ 'id_mt_engine' ];
        $this->id_tms       = $this->jobData[ 'id_tms' ];

        $this->tm_keys      = $this->jobData[ 'tm_keys' ];

        $config = array();
        if ( $this->id_tms == 1 ) {

            /**
             * MyMemory Enabled
             */

            $config[ 'get_mt' ]  = true;
            $config[ 'mt_only' ] = false;
            if ( $this->id_mt_engine != 1 ) {
                /**
                 * Don't get MT contribution from MyMemory ( Custom MT )
                 */
                $config[ 'get_mt' ] = false;
            }

            if( $this->jobData->only_private_tm ){
                $config[ 'onlyprivate' ] = true;
            }

            $_TMS = $this->id_tms;
        } else if ( $this->id_tms == 0 && $this->id_mt_engine == 1 ) {

            /**
             * MyMemory disabled but MT Enabled and it is NOT a Custom one
             * So tell to MyMemory to get MT only
             */
            $config[ 'get_mt' ]  = true;
            $config[ 'mt_only' ] = true;

            $_TMS = 1; /* MyMemory */
        }

        /**
         * if No TM server and No MT selected $_TMS is not defined
         * so we want not to perform TMS Call
         *
         */
        if ( isset( $_TMS ) ) {

            /**
             * @var $tms Engines_MyMemory
             */
            $tms = Engine::getInstance( $_TMS );

            $config = array_merge( $tms->getConfigStruct(), $config );
            $config[ 'segment' ]       = $this->text;
            $config[ 'source' ]        = $this->source;
            $config[ 'target' ]        = $this->target;
            $config[ 'email' ]         = INIT::$MYMEMORY_API_KEY;
            $config[ 'id_user' ]       = array();
            $config[ 'num_result' ]    = $this->num_results;
            $config[ 'isConcordance' ] = $this->concordance_search;

            if ( !$this->concordance_search ) {
                $config[ 'context_before' ] = $this->context_before;
                $config[ 'context_after' ]  = $this->context_after;
            }

            //get job's TM keys
            $this->checkLogin();

            try{

                if ( self::isRevision() ) {
                    $this->userRole = TmKeyManagement_Filter::ROLE_REVISOR;
                }

                $tm_keys = TmKeyManagement_TmKeyManagement::getJobTmKeys($this->tm_keys, 'r', 'tm', $this->uid, $this->userRole );

                if ( is_array( $tm_keys ) && !empty( $tm_keys ) ) {
                    foreach ( $tm_keys as $tm_key ) {
                        $config[ 'id_user' ][ ] = $tm_key->key;
                    }
                }

            } catch ( Exception $e ) {
                $this->result[ 'errors' ][] = [ "code" => -11, "message" => "Cannot retrieve TM keys info." ];
                Log::doLog( $e->getMessage() );

                return;
            }

            $tms_match = $tms->get( $config );
            $tms_match = $tms_match->get_matches_as_array();
        }

        if ( $this->id_mt_engine > 1 /* Request MT Directly */ ) {

            /**
             * @var $mt_engine Engines_MMT
             */
            $mt_engine        = Engine::getInstance( $this->id_mt_engine );
            $config = $mt_engine->getConfigStruct();

            //if a callback is not set only the first argument is returned, get the config params from the callback
            $config = $this->feature_set->filter( 'beforeGetContribution', $config, $mt_engine, $this->jobData );

            $config[ 'segment' ] = $this->text;
            $config[ 'source' ]  = $this->source;
            $config[ 'target' ]  = $this->target;
            $config[ 'email' ]   = INIT::$MYMEMORY_API_KEY;
            $config[ 'segid' ]   = $this->id_segment;

            $mt_result = $mt_engine->get( $config );

            if ( isset( $mt_result['error']['code'] ) ) {
                $mt_result['error']['created_by_type'] = 'MT';
                $this->result[ 'errors' ][] = $mt_result['error'];
                $mt_result = false;
            }

        }
        $matches = array();

        if ( !empty( $tms_match ) ) {
            $matches = $tms_match;
        }

        if ( !empty( $mt_result ) ) {
            $matches[ ] = $mt_result;
            usort( $matches, array( "getContributionController", "__compareScore" ) );
            //this is necessary since usort sorts is ascending order, thus inverting the ranking
            $matches = array_reverse( $matches );
        }

        $matches = array_slice( $matches, 0, $this->num_results );

        /* New Feature only if this is not a MT and if it is a ( 90 =< MATCH < 100 ) */
        ( isset( $matches[ 0 ][ 'match' ] ) ? $firstMatchVal = floatval( $matches[ 0 ][ 'match' ] ) : null );
        if ( isset( $firstMatchVal ) && $firstMatchVal >= 90 && $firstMatchVal < 100 ) {

            $srcSearch    = strip_tags( $this->text );
            $segmentFound = strip_tags( $matches[ 0 ][ 'raw_segment' ] );
            $srcSearch    = mb_strtolower( preg_replace( '#[\x{20}]{2,}#u', chr( 0x20 ), $srcSearch ) );
            $segmentFound = mb_strtolower( preg_replace( '#[\x{20}]{2,}#u', chr( 0x20 ), $segmentFound ) );

            $fuzzy = levenshtein( $srcSearch, $segmentFound ) / log10( mb_strlen( $srcSearch . $segmentFound ) + 1 );

            //levenshtein handle max 255 chars per string and returns -1, so fuzzy var can be less than 0 !!
            if ( $srcSearch == $segmentFound || ( $fuzzy < 2.5 && $fuzzy >= 0 ) ) {

                $qaRealign = new QA( $this->text, html_entity_decode( $matches[ 0 ][ 'raw_translation' ] ) );
                $qaRealign->tryRealignTagID();

                $log_prepend = "CLIENT REALIGN IDS PROCEDURE | ";
                if ( !$qaRealign->thereAreErrors() ) {
                    /*
                    Log::doLog( $log_prepend . " - Requested Segment: " . var_export( $this->__postInput, true) );
                    Log::doLog( $log_prepend . "Fuzzy: " . $fuzzy .  " - Try to Execute Tag ID Realignment." );
                    Log::doLog( $log_prepend . "TMS RAW RESULT:" );
                    Log::doLog( $log_prepend . var_export($matches[0], true) );
                    Log::doLog( $log_prepend . "Realignment Success:");
                    */
                    $matches[ 0 ][ 'segment' ]     = CatUtils::rawxliff2view( $this->text );
                    $matches[ 0 ][ 'translation' ] = CatUtils::rawxliff2view( $qaRealign->getTrgNormalized() );
                    $matches[ 0 ][ 'match' ]       = ( $fuzzy == 0 ? '100%' : '99%' );
                    /*
                    Log::doLog( $log_prepend . "View Segment:     " . var_export($matches[0]['segment'], true) );
                    Log::doLog( $log_prepend . "View Translation: " . var_export($matches[0]['translation'], true) );
					*/
                } else {
                    Log::doLog( $log_prepend . 'Realignment Failed. Skip. Segment: ' . $this->__postInput[ 'id_segment' ] );
                }
            }
        }
        /* New Feature only if this is not a MT and if it is a ( 90 =< MATCH < 100 ) */

        if ( !$this->concordance_search ) {
            //execute these lines only in segment contribution search,
            //in case of user concordance search skip these lines
            $res = $this->setSuggestionReport( $matches );
            if ( is_array( $res ) and array_key_exists( "error", $res ) ) {
                ; // error occurred
            }
            //
        }

        foreach ( $matches as &$match ) {

            if ( strpos( $match[ 'created_by' ], 'MT' ) !== false ) {
                $match[ 'match' ] = 'MT';

                $QA = new PostProcess( $match[ 'raw_segment' ], $match[ 'raw_translation' ] );
                $QA->realignMTSpaces();

                //this should every time be ok because MT preserve tags, but we use the check on the errors
                //for logic correctness
                if ( !$QA->thereAreErrors() ) {
                    $match[ 'raw_translation' ] = $QA->getTrgNormalized();
                    $match[ 'translation' ]     = CatUtils::rawxliff2view( $match[ 'raw_translation' ] );
                } else {
                    Log::doLog( $QA->getErrors() );
                }
            }
            
            if ( $match[ 'created_by' ] == 'MT!' ) {
                $match[ 'created_by' ] = 'MT'; //MyMemory returns MT!
            } elseif ( $match[ 'created_by' ] == 'NeuralMT' ) {
                $match[ 'created_by' ] = 'MT'; //For now do not show differences
            } else {

                $uid = null;
                $this->checkLogin();
                if($this->userIsLogged){
                    $uid = $this->uid;
                }
                $match[ 'created_by' ] = Utils::changeMemorySuggestionSource(
                        $match,
                        $this->jobData['tm_keys'],
                        $this->jobData['owner'],
                        $uid
                );
            }

            $match = $this->_iceMatchRewrite( $match );

            if ( !empty( $match[ 'sentence_confidence' ] ) ) {
                $match[ 'sentence_confidence' ] = round( $match[ 'sentence_confidence' ], 0 ) . "%";
            }

            if ( $this->concordance_search ) {

                $match[ 'segment' ] = strip_tags( html_entity_decode( $match[ 'segment' ] ) );
                $match[ 'segment' ] = preg_replace( '#[\x{20}]{2,}#u', chr( 0x20 ), $match[ 'segment' ] );

                //Do something with &$match, tokenize strings and send to client
                $match[ 'segment' ]     = preg_replace( array_keys( $regularExpressions ), array_values( $regularExpressions ), $match[ 'segment' ] );
                $match[ 'translation' ] = strip_tags( html_entity_decode( $match[ 'translation' ] ) );
            }
        }

        $this->result[ 'data' ][ 'matches' ] = $matches;
    }

    protected function _iceMatchRewrite( $match ){

        if( $match[ 'match' ] == '100%' ){
            list( $lang, ) = explode( '-', $this->jobData[ 'target' ] );
            if( isset( $match[ 'ICE' ] ) && $match[ 'ICE' ] && array_search( $lang, ICES::$iceLockDisabledForTargetLangs ) === false ){
                $match[ 'match' ] = '101%';
            }
            //else do not rewrite the match value
        }

        return $match;

    }

    private function setSuggestionReport( $matches ) {
        if ( count( $matches ) > 0 ) {

            foreach ( $matches as $k => $m ) {
                $matches[ $k ][ 'raw_translation' ] = CatUtils::view2rawxliff( $matches[ $k ][ 'raw_translation' ] );

                if ( $matches[ $k ][ 'created_by' ] == 'MT!' ) {
                    $matches[ $k ][ 'created_by' ] = 'MT'; //MyMemory returns MT!
                } else {
                    $uid = null;
                    $this->checkLogin();
                    if($this->userIsLogged){
                        $uid = $this->uid;
                    }
                    $match[ 'created_by' ] = Utils::changeMemorySuggestionSource(
                            $m,
                            $this->jobData['tm_keys'],
                            $this->jobData['owner'],
                            $uid
                    );
                }

            }

            $suggestions_json_array = json_encode( $matches );
            $match                  = $matches[ 0 ];

            ( !empty( $match[ 'sentence_confidence' ] ) ? $mt_qe = floatval( $match[ 'sentence_confidence' ] ) : $mt_qe = null );

            $data                        = array();
            $data[ 'suggestions_array' ] = $suggestions_json_array;
            $data[ 'suggestion' ]        = $match[ 'raw_translation' ];
            $data[ 'mt_qe' ]             = $mt_qe;
            $data[ 'suggestion_match' ]  = str_replace( '%', '', $match[ 'match' ] );

            $statuses = [ Constants_TranslationStatus::STATUS_NEW ];
            $statuses = $this->feature_set->filter('filterSetSuggestionReportStatuses', $statuses );

            $statuses_condition = implode(' OR ', array_map( function($status) {
                return " status = '$status' " ;
            }, $statuses ) ) ;

            $where = " id_segment= " . (int) $this->id_segment . " and id_job = " . (int) $this->id_job . " AND ( $statuses_condition ) ";

            $db = Database::obtain();

            try {
                $affectedRows = $db->update('segment_translations', $data, $where);
            }
            catch(PDOException $e) {
                log::doLog( $e->getMessage() );
                return $e->getCode() * -1;
            }
            return $affectedRows;
        }

        return 0;
    }

    private static function __compareScore( $a, $b ) {
        if ( floatval( $a[ 'match' ] ) == floatval( $b[ 'match' ] ) ) {
            return 0;
        }

        return ( floatval( $a[ 'match' ] ) < floatval( $b[ 'match' ] ) ? -1 : 1 );
    }

    /**
     * Build tokens to mark with highlight placeholders
     * the source RESULTS occurrences ( correspondences ) with text search incoming from ajax
     *
     * @return array[string => string] $regularExpressions Pattern is in the key and replacement in the value of the array
     *
     */
    protected function tokenizeSourceSearch() {

        $this->text = strip_tags( html_entity_decode( $this->text ) );

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
        $tmp_text = preg_replace( '#[\x{BB}\x{AB}\x{B7}\x{84}\x{82}\x{91}\x{92}\x{93}\x{94}\.\(\)\{\}\[\];:,\"\'\#\+\*]+#u', chr( 0x20 ), $this->text );
        $tmp_text = str_replace( ' - ', chr( 0x20 ), $tmp_text );
        $tmp_text = preg_replace( '#[\x{20}]{2,}#u', chr( 0x20 ), $tmp_text );

        $tokenizedBySpaces  = explode( " ", $tmp_text );
        $regularExpressions = array();
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
        if ( !defined( '_sortByLenDesc' ) ) {
            function _sortByLenDesc( $a, $b ) {
                if ( strlen( $a ) == strlen( $b ) ) {
                    return 0;
                }

                return ( strlen( $b ) < strlen( $a ) ) ? -1 : 1;
            }
        }
        uksort( $regularExpressions, '_sortByLenDesc' );

        return $regularExpressions;
    }
}

