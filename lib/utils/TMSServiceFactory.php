<?
/*
this is the factory for the service methods (i.e., not bound to serving TM query) exposed by MyMemory API
*/

include_once INIT::$UTILS_ROOT . "/CatUtils.php";

class TMSServiceFactory {

	public static function getAPIKeyService(){
		return new TmKeyManagement_LocalAPIKeyService();
	}

	public static function getTMXService($id){
		return new TmKeyManagement_SimpleTMX($id);
	}
}
