<?php

use API\V2\Json\SegmentTranslationMismatches;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 27/04/15
 * Time: 15.47
 *
 */
class getTranslationMismatchesController extends ajaxController {

    private $password;
    private $id_job;

    public function __construct() {

        parent::__construct();

        $filterArgs = [
                'id_segment' => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'id_job'     => [ 'filter' => FILTER_SANITIZE_NUMBER_INT ],
                'password'   => [
                        'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH
                ],
        ];

        $__postInput = filter_input_array( INPUT_POST, $filterArgs );

        //NOTE: This is for debug purpose only,
        //NOTE: Global $_POST Overriding from CLI Test scripts
        //$__postInput = filter_var_array( $_POST, $filterArgs );

        $this->id_segment = $__postInput[ 'id_segment' ];
        $this->id_job     = (int)$__postInput[ 'id_job' ];
        $this->password   = $__postInput[ 'password' ];

        $this->featureSet->loadForProject( Projects_ProjectDao::findByJobId( $this->id_job, 60 * 60 ) );

    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     * @throws Exception
     */
    function doAction() {

        $this->parseIDSegment();

        $sDao                   = new Segments_SegmentDao();
        $Translation_mismatches = $sDao->setCacheTTL( 1 * 60 /* 1 minutes cache */ )->getTranslationsMismatches( $this->id_job, $this->password, $this->id_segment );

        $this->result[ 'code' ] = 1;
        $this->result[ 'data' ] = ( new SegmentTranslationMismatches( $Translation_mismatches, count( $Translation_mismatches ), $this->featureSet ) )->render();

    }


}