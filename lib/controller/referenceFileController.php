<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 10/12/13
 * Time: 16.28
 * 
 */

class referenceFileController extends downloadController {

    protected $job_id;
    protected $job_password;
    protected $segment_id;

    protected $references = array();

    public function __construct(){
        parent::__construct();

        $filterArgs = array(
            'job_id'       => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
            'job_password' => array( 'filter' => FILTER_SANITIZE_STRING, 'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH ),
            'segment_id'   => array( 'filter' => FILTER_SANITIZE_NUMBER_INT ),
        );

        $__postInput     = filter_input_array( INPUT_GET, $filterArgs );

        $this->job_id       = $__postInput[ 'job_id' ]; //can be: search / replace
        $this->job_password = $__postInput[ 'job_password' ];
        $this->segment_id   = $__postInput[ 'segment_id' ];

    }

    protected function _getReferences( $binaries = false ){

        $references = getReferenceSegment( $this->job_id, $this->job_password, $this->segment_id, $binaries );
        $references_meta = unserialize( $references['serialized_reference_meta'] );

        //force cast because if not present we have an "array( false );" and we can merge
        $references_binaries = (array)unserialize( @$references['serialized_reference_binaries'] );

        if( count( $references_meta ) > 1 ){
            /* WARNING there are more than a reference in xliff tag <file>, do something, for now we take the first  */
        } else {

        }

        $this->references = array_merge( (array)$references_meta[0], (array)$references_binaries[0] );

    }

    public function doAction() {

        $this->_getReferences();

    }

    public function finalize() {

        try {
            ob_start("ob_gzhandler");  // compress page before sending

            header("Content-Type: " . $this->references['mime_type'] );
            header("Expires: Thu, 31 Dec 2037 23:55:55 GMT");
            header('Cache-Control: max-age=315360000');
            header("Pragma: cache");


            if( is_file( INIT::$REFERENCE_REPOSITORY . "/" . $this->references['filename'] ) ){
                readfile( INIT::$REFERENCE_REPOSITORY . "/" . $this->references['filename'] );
            } else {
                $this->_getReferences( true );
                $content = base64_decode( $this->references['base64'] );
                @file_put_contents( INIT::$REFERENCE_REPOSITORY . "/" . $this->references['filename'], $content );
                echo $content;
            }

            exit;

        } catch (Exception $e) {
            echo "<pre>";
            print_r($e);
            echo "\n\n\n";
            echo "</pre>";
            exit;
        }
    }

} 