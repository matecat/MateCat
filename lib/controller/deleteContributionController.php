<?php
/**
 * Class engine already included in tms.class.php
 * BUT Not remove include_once INIT::$UTILS_ROOT . "/engines/engine.class.php";
 * Some PHP Version ( Ex: Debian 5.2.6-1+lenny13 does not work )
 */
include_once INIT::$UTILS_ROOT . "/engines/engine.class.php";
include_once INIT::$UTILS_ROOT . "/engines/tms.class.php";
include_once INIT::$MODEL_ROOT . "/queries.php";
include_once INIT::$UTILS_ROOT . '/AjaxPasswordCheck.php';

class deleteContributionController extends ajaxcontroller {

    private $seg;
    private $tra;
    private $source_lang;
    private $target_lang;
    private $id_translator;
    private $password;

    public function __construct() {
        parent::__construct();

        $this->source_lang   = $this->get_from_get_post( 'source_lang' );
        $this->target_lang   = $this->get_from_get_post( 'target_lang' );
        $this->source        = trim( $this->get_from_get_post( 'seg' ) );
        $this->target        = trim( $this->get_from_get_post( 'tra' ) );
        $this->id_translator = trim( $this->get_from_get_post( 'id_translator' ) );
        $this->password      = trim( $this->get_from_get_post( 'password' ) );
        $this->id_job        = trim( $this->get_from_get_post( 'id_job' ) );

    }

    public function doAction() {


        if ( empty( $this->source_lang ) ) {
            $this->result[ 'error' ][ ] = array( "code" => -1, "message" => "missing source_lang" );
        }

        if ( empty( $this->target_lang ) ) {
            $this->result[ 'error' ][ ] = array( "code" => -2, "message" => "missing target_lang" );
        }

        if ( empty( $this->source ) ) {
            $this->result[ 'error' ][ ] = array( "code" => -3, "message" => "missing source" );
        }

        if ( empty( $this->target ) ) {
            $this->result[ 'error' ][ ] = array( "code" => -4, "message" => "missing target" );
        }

        //get Job Infos
        $job_data = getJobData( (int) $this->id_job, $this->password );

        $pCheck = new AjaxPasswordCheck();
        //check for Password correctness
        if( !$pCheck->grantJobAccessByJobData( $job_data, $this->password ) ){
            $this->result[ 'error' ][ ] = array( "code" => -10, "message" => "wrong password" );
            return;
        }

        $config = TMS::getConfigStruct();

        $config[ 'segment' ]       = $this->source;
        $config[ 'translation' ]   = $this->target;
        $config[ 'source_lang' ]   = $this->source_lang;
        $config[ 'target_lang' ]   = $this->target_lang;
        $config[ 'email' ]         = "demo@matecat.com";
        $config[ 'id_user' ]       = $this->id_translator;

        $tms = new TMS( $job_data['id_tms'] );

        $result = $tms->delete( $config );

        $this->result[ 'code' ] = $result;
        $this->result[ 'data' ] = "OK";
    }


}

?>
