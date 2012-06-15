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
//require_once UTILS_ROOT.'/utils.class.php';

require_once UTILS_ROOT.'/log.class.php';
require_once CONTROLLER_ROOT.'/frontController.php';
require_once MODEL_ROOT.'/Database.class.php';
//echo DB_DATABASE;exit;
$db=Database::obtain(DB_SERVER, DB_USER, DB_PASS, DB_DATABASE);
//var_dump($db);
//exit;

$db->connect();

$dispatcher= controllerDispatcher::obtain();
//var_dump ($dispatcher);
$controller=$dispatcher->getController();
//var_dump ($controller);

$controllerRet=$controller->doAction();


if (get_parent_class($controller)=='ajaxcontroller'){	
	$controller->echoJSONResult();
	
}else{	
	$controller->executeTemplate();
}
//var_dump ($controller);exit;
$db->close();

?>
