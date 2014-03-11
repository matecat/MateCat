<?
include_once INIT::$UTILS_ROOT."/CatUtils.php";


class LocalAPIKeyService{

	public function __construct(){

	}


    public function calculateMyMemoryKey($id_translator) {
        $key = getTranslatorKey($id_translator);
        return $key;
    }

    public static function createMyMemoryKey(){

        $newUser = json_decode( file_get_contents( 'http://mymemory.translated.net/api/createranduser' ) );
        if ( empty( $newUser ) || $newUser->error || $newUser->code != 200 ) {
            throw new Exception( "User private key failure.", -1 );
        }

        return $newUser;

    }
	
}

?>
