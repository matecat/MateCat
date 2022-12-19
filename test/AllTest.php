<?php

__halt_compiler();

/**
 * User: Domenico Lupinetti ( Ostico )
 * Date: 25/07/13
 * Time: 10.46
 *
 */
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

        $suite->addTestSuite('Tests_QATest');
        $suite->addTestSuite('Tests_EnginesTest');
        $suite->addTestSuite('Tests_ServerCheckTest');
        $suite->addTestSuite('Tests_PostProcessTest');
        $suite->addTestSuite('Tests_MultiCurlHandlerTest');
        $suite->addTestSuite('Tests_TagPositionTest');
        $suite->addTestSuite('Tests_TmKeyManagementTest');
        // ...

        return $suite;
    }

}
