<?php

class setCurrentSegmentController extends ajaxController {

    protected $password;
    private   $id_job;
    private   $revision_number;

    public function __construct() {

        parent::__construct();

        $filterArgs = [
                'revision_number' => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'id_segment'      => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'id_job'          => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password'        => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
        ];

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->revision_number = $__postInput[ 'revision_number' ];
        $this->id_segment      = $__postInput[ 'id_segment' ];
        $this->id_job          = (int)$__postInput[ 'id_job' ];
        $this->password        = $__postInput[ 'password' ];

    }

    public function doAction() {

        $this->parseIDSegment();

        //get Job Info, we need only a row of jobs ( split )
        $job_data = Jobs_JobDao::getByIdAndPassword( $this->id_job, $this->password );

        if ( empty( $job_data ) ) {
            $this->result[ 'errors' ][] = [ "code" => -10, "message" => "wrong password" ];
        }

        if ( empty( $this->id_segment ) ) {
            $this->result[ 'errors' ][] = [ "code" => -1, "message" => "missing segment id" ];
        }

        if ( !empty( $this->result[ 'errors' ] ) ) {
            //no action on errors
            return;
        }

        $segmentStruct             = new TranslationsSplit_SplitStruct();
        $segmentStruct->id_segment = (int)$this->id_segment;
        $segmentStruct->id_job     = $this->id_job;

        $translationDao  = new TranslationsSplit_SplitDAO( Database::obtain() );
        $currSegmentInfo = $translationDao->read( $segmentStruct );

        /**
         * Split check control
         */
        $isASplittedSegment = false;
        $isLastSegmentChunk = true;

        if ( count( $currSegmentInfo ) > 0 ) {

            $isASplittedSegment = true;
            $currSegmentInfo    = array_shift( $currSegmentInfo );

            //get the chunk number and check whether it is the last one or not
            $isLastSegmentChunk = ( $this->split_num == count( $currSegmentInfo->source_chunk_lengths ) - 1 );

            if ( !$isLastSegmentChunk ) {
                $nextSegmentId = $this->id_segment . "-" . ( $this->split_num + 1 );
            }
        }

        /**
         * End Split check control
         */
        if ( !$isASplittedSegment || $isLastSegmentChunk ) {

            $segmentList = Segments_SegmentDao::getNextSegment( $this->id_segment, $this->id_job, $this->password, $this->revision_number );

            if ( !$this->revision_number ) {
                $nextSegmentId = CatUtils::fetchStatus( $this->id_segment, $segmentList );
            } else {
                $nextSegmentId = CatUtils::fetchStatus( $this->id_segment, $segmentList, Constants_TranslationStatus::STATUS_TRANSLATED );
                if ( !$nextSegmentId ) {
                    $nextSegmentId = CatUtils::fetchStatus( $this->id_segment, $segmentList, Constants_TranslationStatus::STATUS_APPROVED );
                }
            }
        }

        $this->result[ 'code' ] = 1;
        $this->result[ 'data' ] = [];

        //get segment revision informations
        $reviseDao                      = new Revise_ReviseDAO( Database::obtain() );
        $searchReviseStruct             = Revise_ReviseStruct::getStruct();
        $searchReviseStruct->id_job     = $this->id_job;
        $searchReviseStruct->id_segment = $this->id_segment;
        $_dbReviseStruct                = $reviseDao->read( $searchReviseStruct );

        if ( count( $_dbReviseStruct ) > 0 ) {
            $_dbReviseStruct = $_dbReviseStruct[ 0 ];
        } else {
            $_dbReviseStruct = Revise_ReviseStruct::getStruct();
        }

        $_dbReviseStruct = Revise_ReviseStruct::setDefaultValues( $_dbReviseStruct );
        $dbReviseStruct  = self::prepareReviseStructReturnValues( $_dbReviseStruct );

        $this->result[ 'nextSegmentId' ] = $nextSegmentId;
        $this->result[ 'error_data' ]    = $dbReviseStruct;
        $this->result[ 'original' ]      = SubFiltering\Filter::getInstance( $this->featureSet )->fromLayer0ToLayer2( $_dbReviseStruct->original_translation );

    }

    private static function prepareReviseStructReturnValues( $struct ) {
        $return = [];

        $reflect    = new ReflectionClass( 'Constants_Revise' );
        $constCache = $reflect->getConstants();
        foreach ( $constCache as $key => $val ) {
            if ( strpos( $key, "ERR_" ) === false ) {
                unset( $constCache[ $key ] );
            }
        }

        $constCache_keys = array_map( "strtolower", array_keys( $constCache ) );

        foreach ( $struct as $key => $val ) {
            if ( in_array( $key, $constCache_keys ) ) {

                $return[] = [
                        'type'  => $constCache[ strtoupper( $key ) ],
                        'value' => Constants_Revise::$const2clientValues[ $val ]
                ];
            }
        }

        return $return;
    }
}