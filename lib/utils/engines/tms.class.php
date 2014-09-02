<?

error_reporting(E_ALL);
include_once INIT::$UTILS_ROOT . "/engines/engine.class.php";
include_once INIT::$UTILS_ROOT . "/engines/tms_result.class.php";


class TMS extends Engine {

    private $result = array();

    protected static $_config = array(
        'segment'       => null,
        'translation'   => null,
        'tnote'         => null,
        'source_lang'   => null,
        'target_lang'   => null,
        'email'         => null,
        'get_mt'        => 1,
        'id_user'       => null,
        'num_result'    => 3,
        'mt_only'       => false,
        'isConcordance' => false,
        'isGlossary'    => false,
    );

    public static function getConfigStruct(){
        return self::$_config;
    }

    public function __construct($id) {
        parent::__construct($id);
        if ($this->type != "TM") {
            throw new Exception("Engine $id is not a TMS engine, found $this->type");
        }
    }

    //refactory on method sign

    /**
     * @param $_config = array(
     *            'segment'         => null,
     *            'translation'     => null,
     *            'tnote'           => null,
     *            'source_lang'     => null,
     *            'target_lang'     => null,
     *            'email'           => null,
     *            'get_mt'          => 1,
     *            'id_user'         => null,
     *            'num_result'      => 3,
     *            'mt_only'         => false,
     *            'isConcordance'   => false,
     *            'isGlossary'      => false,
     *        );
     *
     * @return TMS_RESULT
     */
    public function get( array $_config ) {

        $parameters = array();
        $parameters['q'] =  $_config['segment'] ;

        $parameters[ 'langpair' ] = $_config['source_lang'] . "|" . $_config['target_lang'];
        $parameters[ 'de' ]       = $_config[ 'email' ];
        $parameters[ 'mt' ]       = $_config[ 'get_mt' ];
        $parameters[ 'numres' ]   = $_config[ 'num_result' ];

        ( $_config['isConcordance'] ? $parameters['conc']   = 'true' : null );
        ( $_config['mt_only']       ? $parameters['mtonly'] = '1' : null );

        if ( !empty( $_config['id_user'] ) ) {
//            $parameters['key'] = $this->calculateMyMemoryKey( $_config['id_user'] );
            $parameters['key'] = implode(",", $_config['id_user']);
        }

        ( !$_config['isGlossary']   ? $apply = "get" : $apply = "gloss_get" );

        $this->doQuery( $apply, $parameters );
        $this->result = new TMS_RESULT($this->raw_result);

        return $this->result;

    }

    public function set( array $_config ) {

        $parameters               = array();
        $parameters[ 'seg' ]      = $_config[ 'segment' ];
        $parameters[ 'tra' ]      = $_config[ 'translation' ];
        $parameters[ 'tnote' ]    = $_config[ 'tnote' ];
        $parameters[ 'langpair' ] = $_config[ 'source_lang' ] . "|" . $_config[ 'target_lang' ];
        $parameters[ 'de' ]       = $_config[ 'email' ];

        if ( !empty( $_config[ 'id_user' ] ) ) {
//            $parameters[ 'key' ] = $this->calculateMyMemoryKey( $_config[ 'id_user' ] );
            $parameters['key'] = implode(",", $_config['id_user']);
        }

        ( !$_config['isGlossary']   ? $apply = "set" : $apply = "gloss_set" );

        $this->doQuery( $apply, $parameters );

        $this->result = new TMS_RESULT( $this->raw_result );

        if ( $this->result->responseStatus != "200" ) {
            return false;
        }
        return true;
    }

    public function delete( array $_config ) {

        $parameters               = array();
        $parameters[ 'seg' ]      = $_config[ 'segment' ];
        $parameters[ 'tra' ]      = $_config[ 'translation' ];
        $parameters[ 'langpair' ] = $_config[ 'source_lang' ] . "|" . $_config[ 'target_lang' ];
        $parameters[ 'de' ]       = $_config[ 'email' ];

        if ( !empty( $_config[ 'id_user' ] ) ) {
//            $parameters[ 'key' ] = $this->calculateMyMemoryKey( $_config[ 'id_user' ] );
            $parameters['key'] = implode(",", $_config['id_user']);
        }

        ( !$_config['isGlossary']   ? $apply = "delete" : $apply = "gloss_delete" );

        $this->doQuery( $apply, $parameters);

        $this->result = new TMS_RESULT($this->raw_result);

        if ($this->result->responseStatus != "200") {
            return false;
        }
        return true;
    }

    public function update( array $_config ){

        $parameters               = array();
        $parameters[ 'seg' ]      = $_config[ 'segment' ];
        $parameters[ 'tra' ]      = $_config[ 'translation' ];
        $parameters[ 'langpair' ] = $_config[ 'source_lang' ] . "|" . $_config[ 'target_lang' ];
        $parameters[ 'tnote' ]    = $_config[ 'tnote' ];

        if ( !empty( $_config[ 'id_user' ] ) ) {
//            $parameters[ 'key' ] = $this->calculateMyMemoryKey( $_config[ 'id_user' ] );
            $parameters['key'] = implode(",", $_config['id_user']);
        }

        $this->doQuery( "gloss_update" , $parameters);

        $this->result = new TMS_RESULT($this->raw_result);

        if ($this->result->responseStatus != "200") {
            return false;
        }
        return true;

    }

//    private function calculateMyMemoryKey($id_translator) {
//
//        $APIKeySrv = TMSServiceFactory::getAPIKeyService();
//        return $APIKeySrv->calculateMyMemoryKey( $id_translator );
//
//    }

}

