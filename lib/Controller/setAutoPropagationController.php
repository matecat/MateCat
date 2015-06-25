<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 27/06/14
 * Time: 12.51
 * 
 */

class setAutoPropagationController extends setTranslationController {

    public function __construct(){

        parent::__construct();

    }

    /**
     * When Called it perform the controller action to retrieve/manipulate data
     *
     * @return mixed
     */
    function doAction() {

        try {

            $this->_checkData( "auto_propagation.log" );

        } catch ( Exception $e ){

            if( $e->getCode() == -1 ){
                Utils::sendErrMailReport( $e->getMessage() );
            }

            Log::doLog( $e->getMessage() );
            return $e->getCode();

        }

        $cookie_key = '_auto-propagation-' . $this->id_job . "-" . $this->password;

        $boolString = (string)(int)$this->propagate;

        $cookieLife = new DateTime();
        $cookieLife->modify( '+15 days' );

        $db = Database::obtain();

        if( $this->propagate ){

            $db->begin();

            $old_translation = getCurrentTranslation( $this->id_job, $this->id_segment );

            //check tag mismatch
            //get original source segment, first
            $segment = getSegment($this->id_segment);

            //compare segment-translation and get results
            $check = new QA( $segment['segment'], $this->translation );
            $check->performConsistencyCheck();

            if( $check->thereAreWarnings() ){
                $err_json = $check->getWarningsJSON();
                $translation = $this->translation;
            } else {
                $err_json = '';
                $translation = $check->getTrgNormalized();
            }

            $TPropagation                             = array();
            $TPropagation[ 'id_job' ]                 = $this->id_job;
            $TPropagation[ 'translation' ]            = $translation;
            $TPropagation[ 'status' ]                 = Constants_TranslationStatus::STATUS_DRAFT;
            $TPropagation[ 'autopropagated_from' ]    = $this->id_segment;
            $_Translation[ 'serialized_errors_list' ] = $err_json;
            $TPropagation[ 'warning' ]                = $check->thereAreWarnings();
            $TPropagation[ 'translation_date' ]       = date( "Y-m-d H:i:s" );
            $TPropagation[ 'segment_hash' ]           = $old_translation[ 'segment_hash' ];

            try {
                propagateTranslation( $TPropagation, $this->jobData, $this->id_segment, true );
                $db->commit();
            } catch ( Exception $e ){
                $db->rollback();
                $msg = $e->getMessage() . "\n\n" . $e->getTraceAsString();
                Log::doLog( $msg );
                Utils::sendErrMailReport( $msg );
            }

        }

        setcookie( $cookie_key, $boolString, $cookieLife->getTimestamp(), "/", $_SERVER['HTTP_HOST'] );

        Log::doLog( "Auto-propagation for already translated segments on Job " . $this->id_job . " set to '"
                . var_export( $this->propagate, true ) ."'. Cookie Expire at " . $cookieLife->format('Y-m-d H:i:s') );

        $this->result['errors'][] = array("code" => 0, "message" => "OK");

    }


} 