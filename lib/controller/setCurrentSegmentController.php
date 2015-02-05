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
        $segmentList   = getNextSegment( $this->id_segment, $this->id_job, $this->password, ( !self::isRevision() ? false : true ) );

        if( !self::isRevision() ){
            $nextSegmentId = fetchStatus( $this->id_segment, $segmentList );
        } else {
            $nextSegmentId = fetchStatus( $this->id_segment, $segmentList, Constants_TranslationStatus::STATUS_TRANSLATED );
            if ( !$nextSegmentId ) {
                $nextSegmentId = fetchStatus( $segmentList, Constants_TranslationStatus::STATUS_APPROVED );
            }
        }


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

        //get segment revision informations
        $reviseDao                      = new Revise_ReviseDAO( Database::obtain() );
        $searchReviseStruct             = Revise_ReviseStruct::getStruct();
        $searchReviseStruct->id_job     = $this->id_job;
        $searchReviseStruct->id_segment = $this->id_segment;
        $_dbReviseStruct = $reviseDao -> read( $searchReviseStruct );

        if(count($_dbReviseStruct) > 0){
            $_dbReviseStruct = $_dbReviseStruct[0];
        }
        else {
            $_dbReviseStruct = Revise_ReviseStruct::getStruct();
        }

        $_dbReviseStruct = Revise_ReviseStruct::setDefaultValues($_dbReviseStruct);
        $dbReviseStruct = self::prepareReviseStructReturnValues($_dbReviseStruct);

        $this->result[ 'nextSegmentId' ] = $nextSegmentId;
        $this->result[ 'error_data' ]    = $dbReviseStruct;
        $this->result[ 'original' ]      = CatUtils::rawxliff2view( $_dbReviseStruct->original_translation );
    }

    private static function prepareReviseStructReturnValues($struct){
        $return = array();

        $reflect = new ReflectionClass( 'Constants_Revise' );
        $constCache =  $reflect->getConstants();
        foreach ($constCache as $key => $val){
            if(strpos($key, "ERR_") === false ) {
                unset($constCache[$key]);
            }
        }

        $constCache_keys = array_map("strtolower", array_keys($constCache));

        foreach ( $struct as $key => $val ) {
            if(in_array($key, $constCache_keys) ){

                $return[] = array(
                    'type' => $constCache[strtoupper($key)],
                    'value' => Constants_Revise::$const2clientValues[$val]
                );
            }
        }

        return $return;
    }
}