<?

error_reporting(E_ALL);
include_once INIT::$UTILS_ROOT . "/engines/engine.class.php";
include_once INIT::$UTILS_ROOT . "/cat.class.php";

class TMS_GET_MATCHES {

    public $id;
    public $raw_segment;
    public $segment;
    public $translation;
    public $target_note;
    public $raw_translation;
    public $quality;
    public $reference;
    public $usage_count;
    public $subject;
    public $created_by;
    public $last_updated_by;
    public $create_date;
    public $last_update_date;
    public $match;

    public function __construct() {
        $args = func_get_args();
        if (empty($args)) {
            throw new Exception("No args defined for " . __CLASS__ . " constructor");
        }

        $match = array();
        if (count($args) == 1 and is_array($args[0])) {
            $match = $args[0];
            if ($match['last-update-date'] == "0000-00-00 00:00:00") {
                $match['last-update-date'] = "0000-00-00";
            }
            if (!empty($match['last-update-date']) and $match['last-update-date'] != '0000-00-00') {
                $match['last-update-date'] = date("Y-m-d", strtotime($match['last-update-date']));
            }

            if (empty($match['created-by'])) {
                $match['created-by'] = "Anonymous";
            }

            $match['match'] = $match['match'] * 100;
            $match['match'] = $match['match'] . "%";
        }

        if (count($args) > 1 and is_array($args[0])) {
            throw new Exception("Invalid arg 1 " . __CLASS__ . " constructor");
        }

        if (count($args) == 5 and !is_array($args[0])) {
            $match['segment'] = $args[0];
            $match['translation'] = $args[1];
            $match['raw_translation'] = $args[1];
            $match['match'] = $args[2];
            $match['created-by'] = $args[3];
            $match['last-update-date'] = $args[4];
        }

        $this->id = array_key_exists('id', $match) ? $match['id'] : '0';
        $this->create_date = array_key_exists('create-date', $match) ? $match['create-date'] : '0000-00-00';
        $this->segment = array_key_exists('segment', $match) ? $match['segment'] : '';
        $this->raw_segment = array_key_exists('raw_segment', $match) ? $match['raw_segment'] : '';
        $this->translation = array_key_exists('translation', $match) ? $match['translation'] : '';
        $this->target_note = array_key_exists('target_note', $match) ? $match['target_note'] : '';
        $this->raw_translation = array_key_exists('raw_translation', $match) ? $match['raw_translation'] : '';
        $this->quality = array_key_exists('quality', $match) ? $match['quality'] : 0;
        $this->reference = array_key_exists('reference', $match) ? $match['reference'] : '';
        $this->usage_count = array_key_exists('usage-count', $match) ? $match['usage-count'] : 0;
        $this->subject = array_key_exists('subject', $match) ? $match['subject'] : '';
        $this->created_by = array_key_exists('created-by', $match) ? $match['created-by'] : '';
        $this->last_updated_by = array_key_exists('last-updated-by', $match) ? $match['last-updated-by'] : '';
        $this->last_update_date = array_key_exists('last-update-date', $match) ? $match['last-update-date'] : '0000-00-00';
        $this->match = array_key_exists('match', $match) ? $match['match'] : 0;
    }

    public function get_as_array() {
        return ((array) $this);
    }

}

class TMS_RESULT {

    public $responseStatus = "";
    public $responseDetails = "";
    public $responseData = "";
    public $matches = array();

    public function __construct($result) {
        $this->responseData = $result['responseData'];
        $this->responseDetails = isset($result['responseDetails']) ? $result['responseDetails'] : '';
        $this->responseStatus = $result['responseStatus'];

        if (is_array($result) and !empty($result) and array_key_exists('matches', $result)) {
            $matches = $result['matches'];
            if (is_array($matches) and !empty($matches)) {
                foreach ($matches as $match) {
                    $match['raw_segment'] = $match['segment'];
                    $match['segment'] = CatUtils::rawxliff2view($match['segment']);
                    $match['raw_translation'] = $match['translation'];
                    $match['translation'] = CatUtils::rawxliff2view($match['translation']);

                    $a = new TMS_GET_MATCHES($match);
                    $this->matches[] = $a;
                }
            }
        }
    }

    public function get_matches_as_array() {
        $a = array();
        foreach ($this->matches as $match) {
            $item = $match->get_as_array();
            $a[] = $item;
        }
        return $a;
    }

    /**
     * Transform one level list to multi level matches based on segment key
     * @return array
     */
    public function get_glossary_matches_as_array(){
        $tmp_vector = array();
        $TMS_RESULT = $this->get_matches_as_array();
        foreach( $TMS_RESULT as $single_match ){
            $tmp_vector[$single_match['segment']][] = $single_match;
        }
        $TMS_RESULT = $tmp_vector;
        return $TMS_RESULT;
    }

    public function get_as_array() {
        return ((array) $this);
    }

}

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
            throw new Exception("not a TMS engine");
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

        //TODO REMOVE THIS PATCH AFTER MyMEMORY Concordance/Glossary FIX : it
        //does not handle properly iso code (en-US)-- COMMIT BUT MUST BE FIXED IN MYMEMORY
        if ( $_config['isConcordance'] || $_config['isGlossary'] ) {
            list( $_config['source_lang'], $trash ) = explode('-', $_config['source_lang'] );
            list( $_config['target_lang'], $trash ) = explode('-', $_config['target_lang'] );
        }
        //TODO REMOVE THIS PATCH AFTER MyMEMORY Concordance/Glossary FIX --

        $parameters[ 'langpair' ] = $_config['source_lang'] . "|" . $_config['target_lang'];
        $parameters[ 'de' ]       = $_config[ 'email' ];
        $parameters[ 'mt' ]       = $_config[ 'get_mt' ];
        $parameters[ 'numres' ]   = $_config[ 'num_result' ];

        ( $_config['isConcordance'] ? $parameters['conc']   = 'true' : null );
        ( $_config['mt_only']       ? $parameters['mtonly'] = '1' : null );

        if ( !empty( $_config['id_user'] ) ) {
            $parameters['key'] = $this->calculateMyMemoryKey( $_config['id_user'] );
        }

        ( !$_config['isGlossary']   ? $apply = "get" : $apply = "gloss_get" );

        $this->doQuery( $apply, $parameters );
        $this->result = new TMS_RESULT($this->raw_result);

        return $this->result;

    }

    public function set( array $_config ) {

        //TODO REMOVE THIS PATCH AFTER MyMEMORY Concordance/Glossary FIX : it
        //does not handle properly iso code (en-US)-- COMMIT BUT MUST BE FIXED IN MYMEMORY
        if ( $_config['isGlossary'] ) {
            list( $_config['source_lang'], $trash ) = explode('-', $_config['source_lang'] );
            list( $_config['target_lang'], $trash ) = explode('-', $_config['target_lang'] );
        }
        //TODO REMOVE THIS PATCH AFTER MyMEMORY Concordance/Glossary FIX --

        $parameters               = array();
        $parameters[ 'seg' ]      = $_config[ 'segment' ];
        $parameters[ 'tra' ]      = $_config[ 'translation' ];
        $parameters[ 'tnote' ]    = $_config[ 'tnote' ];
        $parameters[ 'langpair' ] = $_config[ 'source_lang' ] . "|" . $_config[ 'target_lang' ];
        $parameters[ 'de' ]       = $_config[ 'email' ];

        if ( !empty( $_config[ 'id_user' ] ) ) {
            //$parameters['user'] = $id_user;
            $parameters[ 'key' ] = $this->calculateMyMemoryKey( $_config[ 'id_user' ] );
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

        //TODO REMOVE THIS PATCH AFTER MyMEMORY Concordance/Glossary FIX : it
        //does not handle properly iso code (en-US)-- COMMIT BUT MUST BE FIXED IN MYMEMORY
        if ( $_config['isGlossary'] ) {
            list( $_config['source_lang'], $trash ) = explode('-', $_config['source_lang'] );
            list( $_config['target_lang'], $trash ) = explode('-', $_config['target_lang'] );
        }
        //TODO REMOVE THIS PATCH AFTER MyMEMORY Concordance/Glossary FIX --

        $parameters               = array();
        $parameters[ 'seg' ]      = $_config[ 'segment' ];
        $parameters[ 'tra' ]      = $_config[ 'translation' ];
        $parameters[ 'langpair' ] = $_config[ 'source_lang' ] . "|" . $_config[ 'target_lang' ];
        $parameters[ 'de' ]       = $_config[ 'email' ];

        if ( !empty( $_config[ 'id_user' ] ) ) {
            //$parameters['user'] = $id_user;
            $parameters[ 'key' ] = $this->calculateMyMemoryKey( $_config[ 'id_user' ] );
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

        //TODO REMOVE THIS PATCH AFTER MyMEMORY Concordance/Glossary FIX : it
        //does not handle properly iso code (en-US)-- COMMIT BUT MUST BE FIXED IN MYMEMORY
        if ( $_config['isGlossary'] ) {
            list( $_config['source_lang'], $trash ) = explode('-', $_config['source_lang'] );
            list( $_config['target_lang'], $trash ) = explode('-', $_config['target_lang'] );
        }
        //TODO REMOVE THIS PATCH AFTER MyMEMORY Concordance/Glossary FIX --

        $parameters               = array();
        $parameters[ 'seg' ]      = $_config[ 'segment' ];
        $parameters[ 'tra' ]      = $_config[ 'translation' ];
        $parameters[ 'langpair' ] = $_config[ 'source_lang' ] . "|" . $_config[ 'target_lang' ];
        $parameters[ 'tnote' ]    = $_config[ 'tnote' ];

        if ( !empty( $_config[ 'id_user' ] ) ) {
            //$parameters['user'] = $id_user;
            $parameters[ 'key' ] = $this->calculateMyMemoryKey( $_config[ 'id_user' ] );
        }

        $this->doQuery( "gloss_update" , $parameters);

        $this->result = new TMS_RESULT($this->raw_result);

        if ($this->result->responseStatus != "200") {
            return false;
        }
        return true;

    }

    private function calculateMyMemoryKey($id_translator) {
        $key = getTranslatorKey($id_translator);
        return $key;
    }

    public static function createMyMemoryKey( $id_job ){

        $newUser = json_decode( file_get_contents( 'http://mymemory.translated.net/api/createranduser' ) );
        if ( empty( $newUser ) || $newUser->error || $newUser->code != 200 ) {
            throw new Exception( "User private key failure.", -1 );
        }

        updateTranslatorJob( $id_job, $newUser );

        return $newUser;

    }

}

