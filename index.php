<?php
require_once 'inc/config.inc.php';

INIT::obtain();

require_once INIT::$UTILS_ROOT.'/log.class.php';
require_once INIT::$CONTROLLER_ROOT.'/frontController.php';
require_once INIT::$MODEL_ROOT.'/Database.class.php';
$db=Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
$db->debug=INIT::$DEBUG;
$db->connect();
//var_dump (INIT::$ROOT);exit;

$dispatcher= controllerDispatcher::obtain();
$controller=$dispatcher->getController();
$controller->doAction();
$db->close();

$parentController=get_parent_class($controller);

switch ($parentController){
	case 'ajaxcontroller':
		$controller->echoJSONResult();
		break;
	case 'viewcontroller':
		$controller->executeTemplate();
		break;
	case 'downloadController':
		$controller->download();
}
?>
