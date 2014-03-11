<?
/*
this is the factory for the service methods (i.e., not bound to serving TM query) exposed by MyMemory API
*/

include_once INIT::$UTILS_ROOT . "/CatUtils.php";
include_once INIT::$UTILS_ROOT . "/engines/LocalAPIKeyService.class.php";
include_once INIT::$UTILS_ROOT . "/engines/SimpleTMX.class.php";

class TMSServiceFactory{
	public static function getAPIKeyService(){

		return new LocalAPIKeyService();
	}

	public static function getTMXService($id){
		return new SimpleTMX($id);
	}
}
