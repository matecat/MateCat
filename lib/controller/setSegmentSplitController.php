<?php


class setSegmentSplitController extends ajaxController {

    private $id_segment;
    private $id_job;
    private $job_pass;
    private $split_points_source;
    private $split_points_target;
    private $exec;

    public function __construct() {

        //Session Enabled
        $this->checkLogin();
        //Session Disabled

        $filterArgs = array(
                'id_job'              => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'id_segment'          => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'password'            => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'split_points_source' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'split_points_target' => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ),
                'exec'                => array(
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                )
        );

        $postInput = filter_input_array( INPUT_POST, $filterArgs );

        $this->id_job              = $postInput[ 'id_job' ];
        $this->id_segment          = $postInput[ 'id_segment' ];
        $this->job_pass            = $postInput[ 'password' ];
        $this->split_points_source = json_decode( $postInput[ 'split_points_source' ], true );
        $this->split_points_target = json_decode( $postInput[ 'split_points_target' ], true );
        $this->exec                = $postInput[ 'exec' ];

        if ( !$this->userIsLogged ) {
            $this->result[ 'errors' ][ ] = array(
                    'code'    => -2,
                    'message' => "Login is required to perform this action"
            );
        }

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
        if ( is_null( $this->split_points_source ) ) {
            $this->result[ 'errors' ][ ] = array(
                    'code'    => -6,
                    'message' => 'Invalid split_points_source json'
            );
        }
        else if ( empty( $this->split_points_source ) ) {
            $this->result[ 'errors' ][ ] = array(
                    'code'    => -6,
                    'message' => 'split_points_source cannot be empty'
            );
        }

    }

    public function doAction() {

        if ( !empty( $this->result[ 'errors' ] ) ) {
            return;
        }

        //save the 2 arrays in the DB

        $translationStruct = Translations_TranslationStruct::getStruct();

        $translationStruct->id_segment          = $this->id_segment;
        $translationStruct->id_job              = $this->id_job;
        $translationStruct->split_points_source = $this->split_points_source;
        $translationStruct->split_points_target = $this->split_points_target;

        $translationDao = new Translations_TranslationsDAO( Database::obtain() );
        $result = $translationDao->update($translationStruct);

        if($result instanceof Translations_TranslationStruct){
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

        $translationStruct = Translations_TranslationStruct::getStruct();

        $translationStruct->id_segment          = $this->id_segment;
        $translationStruct->id_job              = $this->id_job;
        $translationStruct->split_points_source = $this->split_points_source;
        $translationStruct->split_points_target = $this->split_points_target;

        $translationDao = new Translations_TranslationsDAO( Database::obtain() );
        $result = $translationDao->update($translationStruct);

        if($result instanceof Translations_TranslationStruct){
            //return success
            $this->result['data'] = 'OK';
        }
        else{
            Log::doLog("Failed split segment.");
            Log::doLog($translationStruct);
        }
    }
}


