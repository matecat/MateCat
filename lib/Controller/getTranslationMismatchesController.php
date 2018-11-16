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

        $this->id_segment = $__postInput[ 'id_segment' ];
        $this->id_job     = (int)$__postInput[ 'id_job' ];
        $this->password   = $__postInput[ 'password' ];

        $this->featureSet->loadForProject( Projects_ProjectDao::findByJobId( $this->id_job, 60 * 60 ) );

    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     */
    function doAction() {

        $this->parseIDSegment();

        $_thereArePossiblePropagations = countThisTranslatedHashInJob( $this->id_job, $this->password, $this->id_segment );
        $thereArePossiblePropagations  = intval( $_thereArePossiblePropagations[ 'available' ] );

        $Translation_mismatches = array();
        if ( $thereArePossiblePropagations ) {
            $Translation_mismatches = getTranslationsMismatches( $this->id_job, $this->password, $this->id_segment );
        }

        $this->result[ 'code' ] = 1;
        $this->result[ 'data' ] = ( new SegmentTranslationMismatches( $Translation_mismatches, $thereArePossiblePropagations, $this->featureSet ) )->render();

    }


}