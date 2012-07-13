<?php
error_reporting (E_ALL);
/*
print_r ($_REQUEST);
print_r ($_SERVER['HTTP_HOST']);exit;
echo "<pre>";
print_r ($_SERVER);
echo "</pre>";

exit;
*/
require_once 'inc/config.inc.php';

INIT::obtain();
//require_once UTILS_ROOT.'/utils.class.php';

require_once INIT::$UTILS_ROOT.'/log.class.php';
require_once INIT::$CONTROLLER_ROOT.'/frontController.php';
require_once INIT::$MODEL_ROOT.'/Database.class.php';
//echo DB_DATABASE;exit;
$db=Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
//var_dump($db);
//exit;
$db->debug=INIT::$DEBUG;
$db->connect();

$dispatcher= controllerDispatcher::obtain();
//var_dump ($dispatcher);
$controller=$dispatcher->getController();
//var_dump ($controller);
$controller->doAction();


if (get_parent_class($controller)=='ajaxcontroller'){	
	$controller->echoJSONResult();
}else{	
	$controller->executeTemplate();
}
//var_dump ($controller);exit;
$db->close();

?>
