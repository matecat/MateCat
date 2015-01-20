<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 19/01/15
 * Time: 16.50
 */
class setRevisionController extends ajaxController {

    private $id_job;
    private $id_segment;
    private $err_typing;
    private $err_translation;
    private $err_terminology;
    private $err_quality;
    private $err_style;

    private static $accepted_values = array(
            Constants_Revise::NONE,
            Constants_Revise::MINOR,
            Constants_Revise::MAJOR
    );

    public function __construct() {
        $filterArgs = array(
                'job'             => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'segment'         => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
                'err_typing'      => array(
                        'filter'  => FILTER_CALLBACK,
                        'options' => array( "setRevisionController", "sanitizeFieldValue" )
                ),
                'err_translation' => array(
                        'filter'  => FILTER_CALLBACK,
                        'options' => array( "setRevisionController", "sanitizeFieldValue" )
                ),
                'err_terminology' => array(
                        'filter'  => FILTER_CALLBACK,
                        'options' => array( "setRevisionController", "sanitizeFieldValue" )
                ),
                'err_quality'     => array(
                        'filter'  => FILTER_CALLBACK,
                        'options' => array( "setRevisionController", "sanitizeFieldValue" )
                ),
                'err_style'       => array(
                        'filter'  => FILTER_CALLBACK,
                        'options' => array( "setRevisionController", "sanitizeFieldValue" )
                )
        );

        $postInput             = filter_input_array( INPUT_POST, $filterArgs );
        $this->id_job          = $postInput[ 'job' ];
        $this->id_segment      = $postInput[ 'segment' ];
        $this->err_typing      = $postInput[ 'err_typing' ];
        $this->err_translation = $postInput[ 'err_translation' ];
        $this->err_terminology = $postInput[ 'err_terminology' ];
        $this->err_quality     = $postInput[ 'err_quality' ];
        $this->err_style       = $postInput[ 'err_style' ];

        if ( empty( $this->id_job ) ) {
            $this->result[ 'errors' ][ ] = array( 'code' => -1, 'message' => 'Job ID missing' );
        }

        if ( empty( $this->id_segment ) ) {
            $this->result[ 'errors' ][ ] = array( 'code' => -2, 'message' => 'Segment ID missing' );
        }

    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     */
    public function doAction() {
        if ( !empty( $this->result[ 'errors' ] ) ) {
            return;
        }

        //store segment revision in DB
        $revisionStruct = new Revise_ReviseStruct(
                array(
                        'id_job'          => $this->id_job,
                        'id_segment'      => $this->id_segment,
                        'err_typing'      => $this->err_typing,
                        'err_translation' => $this->err_translation,
                        'err_terminology' => $this->err_terminology,
                        'err_quality'     => $this->err_quality,
                        'err_style'       => $this->err_style
                )
        );

        $reviseDAO = new Revise_ReviseDAO( Database::obtain() );

        try {
            $reviseDAO->create( $revisionStruct );
            $this->result[ 'data' ][ 'message' ] = 'OK';
        } catch ( Exception $e ) {
            Log::doLog( __CLASS__ . "::" . __METHOD__ . " -> " . $e->getMessage() );
            $this->result[ 'errors' ] [ ] = array( 'code' => -3, 'message' => "Insert failed" );
            return;
        }
    }

    /**
     * @param $fieldVal string
     *
     * @return string The sanitized field
     */
    private static function sanitizeFieldValue( $fieldVal ) {
        //if $fieldVal is not one of the accepted values, force it to "none"
        if ( !in_array( $fieldVal, self::$accepted_values ) ) {
            return Constants_Revise::NONE;
        }

        return $fieldVal;
    }

} 