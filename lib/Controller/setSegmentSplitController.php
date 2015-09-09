<?php


class setSegmentSplitController extends ajaxController {

    private $id_job;
    private $job_pass;
    private $segment;
    private $target;
    private $exec;

    public function __construct() {

        parent::__construct();

        //Session Enabled
        $this->checkLogin();
        //Session Disabled

        $filterArgs = array(
                'id_job'              => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'id_segment'          => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'password'            => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'segment' => array(
                        'filter' => FILTER_UNSAFE_RAW
                ),
                'target' => array(
                        'filter' => FILTER_UNSAFE_RAW
                ),
                'exec'                => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                )
        );

        $postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->id_job              = $postInput[ 'id_job' ];
        $this->id_segment          = $postInput[ 'id_segment' ];
        $this->job_pass            = $postInput[ 'password' ];
        $this->segment             = $postInput[ 'segment' ];
        $this->target              = $postInput[ 'target' ];
        $this->exec                = $postInput[ 'exec' ];

//        if ( !$this->userIsLogged ) {
//            $this->result[ 'errors' ][ ] = array(
//                    'code'    => -2,
//                    'message' => "Login is required to perform this action"
//            );
//        }

        if ( empty( $this->id_job ) ) {
            $this->result[ 'errors' ][ ] = array(
                    'code'    => -3,
                    'message' => 'Invalid job id'
            );
        }

        if ( empty( $this->id_segment ) ) {
            $this->result[ 'errors' ][ ] = array(
                    'code'    => -4,
                    'message' => 'Invalid segment id'
            );
        }

        if ( empty( $this->job_pass ) ) {
            $this->result[ 'errors' ][ ] = array(
                    'code'    => -5,
                    'message' => 'Invalid job password'
            );
        }

        //this checks that the json is valid, but not its content
        if ( is_null( $this->segment ) ) {
            $this->result[ 'errors' ][ ] = array(
                    'code'    => -6,
                    'message' => 'Invalid source_chunk_lengths json'
            );
        }
        /*
        else if ( empty( $this->source_chunk_lengths ) ) {
            $this->result[ 'errors' ][ ] = array(
                    'code'    => -6,
                    'message' => 'source_chunk_lengths cannot be empty'
            );
        }
        */

    }

    public function doAction() {

        if ( !empty( $this->result[ 'errors' ] ) ) {
            return;
        }

        //save the 2 arrays in the DB

        $translationStruct = TranslationsSplit_SplitStruct::getStruct();

        $translationStruct->id_segment          = $this->id_segment;
        $translationStruct->id_job              = $this->id_job;

        list( $this->segment, $translationStruct->source_chunk_lengths ) = CatUtils::parseSegmentSplit( CatUtils::view2rawxliff( $this->segment ) );

        /* Fill the statuses with DEFAULT DRAFT VALUES */
        $pieces = ( count( $translationStruct->source_chunk_lengths ) > 1 ? count( $translationStruct->source_chunk_lengths )  -1 : 1 );
        $translationStruct->target_chunk_lengths = array( 'len' => array( 0 ), 'statuses' => array_fill( 0, $pieces, Constants_TranslationStatus::STATUS_DRAFT ) );

        $translationDao = new TranslationsSplit_SplitDAO( Database::obtain() );
        $result = $translationDao->update($translationStruct);

        if($result instanceof TranslationsSplit_SplitStruct){
            //return success
            $this->result['data'] = 'OK';
        }
        else{
            Log::doLog("Failed while splitting/merging segment.");
            Log::doLog($translationStruct);
        }
    }

    private function split() {

        //save the 2 arrays in the DB

//        $translationStruct = TranslationsSplit_SplitStruct::getStruct();
//
//        $translationStruct->id_segment          = $this->id_segment;
//        $translationStruct->id_job              = $this->id_job;
//
//        list( $this->segment, $translationStruct->source_chunk_lengths ) = CatUtils::parseSegmentSplit( CatUtils::view2rawxliff( $this->segment ) );
//
//        /* Fill the statuses with DEFAULT DRAFT VALUES */
//        $pieces = ( count( $translationStruct->source_chunk_lengths ) > 1 ? count( $translationStruct->source_chunk_lengths )  -1 : 1 );
//        $translationStruct->target_chunk_lengths = array( 'len' => array( 0 ), 'statuses' => array_fill( 0, $pieces, Constants_TranslationStatus::STATUS_DRAFT ) );
//
//        $translationDao = new TranslationsSplit_SplitDAO( Database::obtain() );
//        $result = $translationDao->update($translationStruct);
//
//        if($result instanceof TranslationsSplit_SplitStruct){
//            //return success
//            $this->result['data'] = 'OK';
//        }
//        else{
//            Log::doLog("Failed while splitting/merging segment.");
//            Log::doLog($translationStruct);
//        }

    }
}


