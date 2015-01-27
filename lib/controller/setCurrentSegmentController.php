<?php
include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . '/AjaxPasswordCheck.php';

class setCurrentSegmentController extends ajaxController {

    private $id_segment;
    private $id_job;


    public function __construct() {

        parent::__construct();

        $filterArgs = array(
                'id_segment' => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'id_job'     => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'password'   => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
        );

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->id_segment = (int)$__postInput[ 'id_segment' ];
        $this->id_job     = (int)$__postInput[ 'id_job' ];
        $this->password   = $__postInput[ 'password' ];

    }

    public function doAction() {

        //get Job Infos
        $job_data = getJobData( (int)$this->id_job );

        $pCheck = new AjaxPasswordCheck();
        //check for Password correctness
        if ( !$pCheck->grantJobAccessByJobData( $job_data, $this->password ) ) {
            $this->result[ 'error' ][ ] = array( "code" => -10, "message" => "wrong password" );
        }

        if ( empty( $this->id_segment ) ) {
            $this->result[ 'error' ][ ] = array( "code" => -1, "message" => "missing segment id" );
        }

        if ( empty( $this->id_job ) ) {
            $this->result[ 'error' ][ ] = array( "code" => -2, "message" => "missing Job id" );
        }

        if ( !empty( $this->result[ 'error' ] ) ) {
            //no action on errors
            return;
        }

        $insertRes     = setCurrentSegmentInsert( $this->id_segment, $this->id_job, $this->password );
        $nextSegmentId = getNextUntranslatedSegment( $this->id_segment, $this->id_job, $this->password );

        $_thereArePossiblePropagations = countThisTranslatedHashInJob( $this->id_job, $this->password, $this->id_segment );
        $thereArePossiblePropagations  = intval( $_thereArePossiblePropagations[ 'available' ] );

        $Translation_mismatches = array();
        if ( $thereArePossiblePropagations ) {
            $Translation_mismatches = getTranslationsMismatches( $this->id_job, $this->password, $this->id_segment );
        }

        $result = array(
                'editable'       => array(),
                'not_editable'   => array(),
                'prop_available' => $thereArePossiblePropagations
        );

        foreach ( $Translation_mismatches as $position => $row ) {

            if ( $row[ 'editable' ] ) {
                $result[ 'editable' ][ ] = array(
                        'translation' => CatUtils::rawxliff2view( $row[ 'translation' ] ),
                        'TOT'         => $row[ 'TOT' ],
                        'involved_id' => explode( ",", $row[ 'involved_id' ] )
                );
            } else {
                $result[ 'not_editable' ][ ] = array(
                        'translation' => CatUtils::rawxliff2view( $row[ 'translation' ] ),
                        'TOT'         => $row[ 'TOT' ],
                        'involved_id' => explode( ",", $row[ 'involved_id' ] )
                );
            }

        }

        $this->result[ 'code' ] = 1;
        $this->result[ 'data' ] = $result;

        $nSegment = array( 'id' => null );
        if ( isset( $nextSegmentId[ 0 ][ 'id' ] ) ) {
            //if there are results check for next id,
            //otherwise get the first one in the list
            $nSegment = $nextSegmentId[ 0 ];
            foreach ( $nextSegmentId as $seg ) {
                if ( $seg[ 'id' ] > $this->id_segment ) {
                    $nSegment = $seg;
                    break;
                }
            }
        }

        //get segment revision informations
        $reviseDao                      = new Revise_ReviseDAO( Database::obtain() );
        $searchReviseStruct             = Revise_ReviseStruct::getStruct();
        $searchReviseStruct->id_job     = $this->id_job;
        $searchReviseStruct->id_segment = $this->id_segment;
        $dbReviseStruct = $reviseDao -> read( $searchReviseStruct );

        if(count($dbReviseStruct) > 0){
            $dbReviseStruct = $dbReviseStruct[0];
        }
        else {
            $dbReviseStruct = Revise_ReviseStruct::getStruct();
        }

        $dbReviseStruct = Revise_ReviseStruct::setDefaultValues($dbReviseStruct);
        $dbReviseStruct = self::prepareReviseStructReturnValues($dbReviseStruct);

        $this->result[ 'nextSegmentId' ] = $nSegment[ 'id' ];
        $this->result[ 'error_data' ] = $dbReviseStruct;
    }

    private static function prepareReviseStructReturnValues($struct){
        $return = array();

        foreach ( $struct as $key => $val ) {
            if(strpos($key, "err_") > -1){

                $key = ucfirst(
                        str_replace(
                                array("err_","_"),
                                array("",""),
                                $key
                        )
                );
                //TODO: remove this hard-coded replacement
                if($key == "Quality") $key = "Language Quality";

                $return[] = array(
                    'type' => $key,
                    'value' => Constants_Revise::$const2clientValues[$val]
                );
            }
        }

        return $return;
    }
}