<?php

include_once INIT::$UTILS_ROOT . "/engines/mt.class.php";
include_once INIT::$UTILS_ROOT . "/engines/tms.class.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";
include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . "/QA.php";

class getContributionController extends ajaxcontroller {

	private $id_segment;
	private $id_job;
	private $num_results;
	private $text;
	private $source;
	private $target;
	private $id_mt_engine;
	private $id_tms;
	private $id_translator;

    private $__postInput = array();

	public function __construct() {
		parent::__construct();

        $filterArgs = array(
            'id_segment'     => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'id_job'         => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'num_results'    => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'text'           => array( 'filter' => FILTER_UNSAFE_RAW ),
            'id_translator'  => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW ),
            'is_concordance' => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
            'from_target'    => array( 'filter' => FILTER_VALIDATE_BOOLEAN ),
        );

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

		if ($this->id_translator == 'unknown_translator') {
			$this->id_translator = "";
		}

	}

	public function doAction() {

        if( !$this->concordance_search ){
            //execute these lines only in segment contribution search,
            //in case of user concordance search skip these lines
            //because segment can be optional
            if (empty($this->id_segment)) {
                $this->result['errors'][] = array("code" => -1, "message" => "missing id_segment");
            }
        }

		if (is_null($this->text) || $this->text === '' ) {
			$this->result['errors'][] = array("code" => -2, "message" => "missing text");
		}

        if (empty($this->id_job)) {
            $this->result['errors'][] = array("code" => -3, "message" => "missing id_job");
        }


		if (empty($this->num_results)) {
			$this->num_results = INIT::$DEFAULT_NUM_RESULTS_FROM_TM;
		}

		if (!empty($this->result['errors'])) {
			return -1;
		}

		$st = getJobData($this->id_job);


        /*
         * string manipulation strategy
         *
         */
        if ( !$this->concordance_search ) {
            //
            $this->text = CatUtils::view2rawxliff( $this->text );
            $this->source = $st[ 'source' ];
            $this->target = $st[ 'target' ];

        } else {

            $regularExpressions = $this->tokenizeSourceSearch( $st );

        }

		$this->id_mt_engine = $st['id_mt_engine'];
		$this->id_tms = $st['id_tms'];

		$tms_match = array();
		if (!empty($this->id_tms)) {

			$mt_from_tms = 1;
			if (!empty($this->id_mt_engine) and $this->id_mt_engine != 1) {
				$mt_from_tms = 0;
			}

			$tms = new TMS($this->id_tms);
			$tms_match = $tms->get($this->text, $this->source, $this->target, "demo@matecat.com", $mt_from_tms, $this->id_translator, $this->num_results );
		}

		$mt_res = array();
		$mt_match = "";
		if (!empty($this->id_mt_engine) and $this->id_mt_engine != 1) {
			$mt = new MT($this->id_mt_engine);
			$mt_result = $mt->get($this->text, $this->source, $this->target);

			if ($mt_result[0] < 0) {
				$mt_match = '';
			} else {
				$mt_match = $mt_result[1];
				$penalty = $mt->getPenalty();
				$mt_score = 100 - $penalty;
				$mt_score.="%";

				$mt_match_res = new TMS_GET_MATCHES($this->text, $mt_match, $mt_score, "MT-" . $mt->getName(), date("Y-m-d"));

				$mt_res = $mt_match_res->get_as_array();
			}
		}
		$matches = array();

		if (!empty($tms_match)) {
			$matches = $tms_match;
		}

		if (!empty($mt_match)) {
			$matches[] = $mt_res;
			usort( $matches, array( "getContributionController", "__compareScore" ) );
		}
		$matches = array_slice( $matches, 0, $this->num_results );

        ( isset($matches[0]['match']) ? $firstMatchVal = floatval( $matches[0]['match'] ) : null );
        if( isset( $firstMatchVal ) && $firstMatchVal >= 90 && $firstMatchVal < 100 ){

            $srcSearch = strip_tags($this->text);
            $segmentFound = strip_tags($matches[0]['raw_segment']);
            $srcSearch = mb_strtolower( preg_replace( '#[\x{20}]{2,}#u', chr( 0x20 ), $srcSearch ) );
            $segmentFound = mb_strtolower( preg_replace( '#[\x{20}]{2,}#u', chr( 0x20 ), $segmentFound ) );

            $fuzzy = levenshtein($srcSearch, $segmentFound) / log10( mb_strlen( $srcSearch . $segmentFound ) +1 );

            if( $srcSearch == $segmentFound || $fuzzy < 2.5 ){

                $qaRealign = new QA( $this->text, html_entity_decode( $matches[0]['raw_translation'] ) );
                $qaRealign->tryRealignTagID();

                $log_prepend = "CLIENT REALIGN IDS PROCEDURE | ";
                if( !$qaRealign->thereAreErrors() ){

                    Log::doLog( $log_prepend . " - Requested Segment: " . var_export( $this->__postInput, true) );
                    Log::doLog( $log_prepend . "Fuzzy: " . $fuzzy .  " - Try to Execute Tag ID Realignment." );
                    Log::doLog( $log_prepend . "TMS RAW RESULT:" );
                    Log::doLog( $log_prepend . var_export($matches[0], true) );

                    Log::doLog( $log_prepend . "Realignment Success:");
                    $matches[0]['segment'] = CatUtils::rawxliff2view( $this->text );
                    $matches[0]['translation'] = CatUtils::rawxliff2view( $qaRealign->getTrgNormalized() );
                    $matches[0]['match'] = ( $fuzzy == 0 ? '100%' : '99%' );
                    Log::doLog( $log_prepend . "View Segment:     " . var_export($matches[0]['segment'], true) );
                    Log::doLog( $log_prepend . "View Translation: " . var_export($matches[0]['translation'], true) );

                } else {
                    Log::doLog( $log_prepend . 'Realignment Failed. Skip. Segment: ' . $this->__postInput['id_segment'] );
                }

            }

        }

        if( !$this->concordance_search ){
            //execute these lines only in segment contribution search,
            //in case of user concordance search skip these lines
            $res = $this->setSuggestionReport($matches);
            if (is_array($res) and array_key_exists("error", $res)) {
                ; // error occurred
            }
            //
        }

		foreach ($matches as &$match) {
			if (strpos($match['created_by'], 'MT') !== false) {
				$match['match'] = 'MT';
			}
			if ($match['created_by'] == 'MT!') {
				$match['created_by'] = 'MT'; //MyMemory returns MT!
			}

            if( $this->concordance_search ){

                $match['segment'] = strip_tags( html_entity_decode( $match['segment'] ) );
                $match['segment'] = preg_replace( '#[\x{20}]{2,}#u', chr( 0x20 ), $match['segment'] );

                //Do something with &$match, tokenize strings and send to client
                $match['segment'] = preg_replace( array_keys( $regularExpressions ), array_values( $regularExpressions ), $match['segment'] );
                $match['translation'] = strip_tags( html_entity_decode( $match['translation'] ) );

            }

		}

        $this->result['data']['matches'] = $matches;

	}

	private function setSuggestionReport($matches) {
		if (count($matches) > 0) {

            foreach ( $matches as $k => $m ) {
                $matches[ $k ][ 'raw_translation' ] = CatUtils::view2rawxliff( $matches[ $k ][ 'raw_translation' ] );
            }

            $suggestions_json_array = json_encode( $matches );
            $match = $matches[ 0 ];

            $suggestion        = $match[ 'raw_translation' ];
            $suggestion_match  = $match[ 'match' ];
            $suggestion_source = $match[ 'created_by' ];
            $ret               = CatUtils::addTranslationSuggestion( $this->id_segment, $this->id_job, $suggestions_json_array, $suggestion, $suggestion_match, $suggestion_source );
            return $ret;
		}

		return 0;
	}

    private static function __compareScore($a, $b) {
        if( floatval($a['match']) == floatval($b['match']) ){ return 0; }
        return ( floatval($a['match']) < floatval($b['match']) ? -1 : 1);
    }

    /**
     * Build tokens to mark with highlight placeholders
     * the source RESULTS occurrences ( correspondences ) with text search incoming from ajax
     *
     * @param $sourceStringSearch
     * @return array[string => string] $regularExpressions Pattern is in the key and replacement in the value of the array
     *
     */
    protected function tokenizeSourceSearch( $sourceStringSearch ) {

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
        $tmp_text = preg_replace( '#[\x{BB}\x{AB}\x{B7}\x{84}\x{82}\x{91}\x{92}\x{93}\x{94}\.\(\)\{\}\[\];:,\"\'\#\+\-\*]+#u', chr( 0x20 ), $this->text );
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
                if ( strlen( $a ) == strlen( $b ) ) { return 0; }
                return ( strlen( $b ) < strlen( $a ) ) ? -1 : 1;
            }
        }
        uksort( $regularExpressions, '_sortByLenDesc' );

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
            $this->source = $sourceStringSearch[ 'target' ];
            $this->target = $sourceStringSearch[ 'source' ];
        } else {
            $this->source = $sourceStringSearch[ 'source' ];
            $this->target = $sourceStringSearch[ 'target' ];
        }

        return $regularExpressions;

    }

}


?>
