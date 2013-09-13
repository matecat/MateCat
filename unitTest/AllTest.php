<?php

/**
 * User: Domenico Lupinetti ( Ostico )
 * Date: 25/07/13
 * Time: 10.46
 *
 */
include '/var/www/cattool/inc/config.inc.php';
@INIT::obtain();
include '/var/www/cattool/lib/utils/log.class.php';
include '/var/www/cattool/lib/utils/utils.class.php';
include '/var/www/cattool/lib/utils/cat.class.php';
include '/var/www/cattool/lib/utils/QA.php';

require_once "PHPUnit/Autoload.php";

class Framework_AllTests {

    public static function autoload($className) {
        $className = ltrim($className, '\\');
        $fileName = '';
        $namespace = '';
        if ($lastNsPos = strrpos($className, '\\')) {
            $namespace = substr($className, 0, $lastNsPos);
            $className = substr($className, $lastNsPos + 1);
            $fileName = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
        }
        $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';
        @include $fileName;
    }

    public static function suite() {
        
        spl_autoload_register('Framework_AllTests::autoload', true);

        $suite = new PHPUnit_Framework_TestSuite('PHPUnit Suite');

        $suite->addTestSuite('QATest');
        // ...

        return $suite;
    }

}

