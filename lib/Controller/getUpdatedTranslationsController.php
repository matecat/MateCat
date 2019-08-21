<?php

class getUpdatedTranslationsController extends ajaxController {

    private $last_timestamp;
    private $first_segment;
    private $last_segment;
    private $id_job;
    private $password;

    public function __construct() {

        parent::__construct();

        $filterArgs = array(
                'first_segment'  => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'last_segment'   => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'last_timestamp' => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'id_job'         => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'password'       => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH )
        );

        $__postInput = filter_input_array(INPUT_POST, $filterArgs);

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        if ( empty( $__postInput[ 'last_timestamp' ] )
                || empty( $__postInput[ 'first_segment' ] )
                || empty( $__postInput[ 'last_segment' ] )
                || empty( $__postInput[ 'id_job' ] )
                || empty( $__postInput[ 'password' ] )
        ) {
            $this->result[ 'data' ] = array();
            return;
        }

        $this->last_timestamp = $__postInput[ 'last_timestamp' ] / 1000;
        $this->first_segment  = $__postInput[ 'first_segment' ];
        $this->last_segment   = $__postInput[ 'last_segment' ];
        $this->id_job         = $__postInput[ 'id_job' ];
        $this->password       = $__postInput[ 'password' ];

    }

    public function doAction() {

        $jobData = Jobs_JobDao::getByIdAndPassword( $this->id_job, $this->password );
        //check for Password correctness
        if ( empty( $jobData ) ) {
            $this->result['error'][] = array("code" => -10, "message" => "wrong password");
            return;
        }

        $data                   = Translations_SegmentTranslationDao::getUpdatedTranslations( $this->last_timestamp, $this->first_segment, $this->last_segment, $this->id_job );
        $this->result[ 'data' ] = $data;
    }

}