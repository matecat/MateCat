<?php

//print_r ($_SERVER['HTTP_HOST']);
if (!defined('CONST')) {
    define('CONST', 1);

    define('DB_DATABASE', "translated"); //database name

    define('DB_DATABASE_SANDBOX', "matecat_sandbox"); //database name

    if (!defined('ROOT')) {
       // $root = dirname(getcwd());
        $root = getcwd();
        define('ROOT', $root);
        // echo ROOT; exit;
        switch ($_SERVER['HTTP_HOST']) {
            case ('matecat.pi'):
            case ('matecat.local'):
                define('ROOT_URL', '/index.php');
                //define('DB_SERVER', "192.168.1.53"); //database server
                 define('DB_SERVER', "10.30.1.241");
                define('DB_USER', "translated"); //database login name
                define('DB_PASS', "azerty1"); //database login password
                break;
            case 'cattooldemo.matecat.com':
	    case 'matecat.translated.home':
            case 'matecat.com':
            case 'www.matecat.com':
                define('DB_SERVER', "10.30.1.241"); //database server
                define('DB_USER', "matecat"); //database login name
                define('DB_PASS', "azerty1"); //database login password


                break;
            default :
                die(__FILE__ . " " . __LINE__ . " : unrecognided domain. Unable to set ROOT");
        }
    }

    //     echo "ecco " . ROOT;exit;
    //print_r ($_SERVER);exit;

    define('LOG_REPOSITORY', ROOT . "/log_archive");
    define('TEMPLATE_ROOT', ROOT . '/lib/view');
    define('MODEL_ROOT', ROOT . '/lib/model');
    define('CONTROLLER_ROOT', ROOT . '/lib/controller');
    define('UTILS_ROOT', ROOT . '/lib/utils');



    //define('PROJECTS_ROOT', "/home/translated/projects");
}
?>
