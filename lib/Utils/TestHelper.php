<?php

class TestHelper {
    /**
     * @var FixturesLoader
     */
    public static $FIXTURES ;
    /**
     * @var SchemaCopy
     */
    public static $SCHEMA_HELPER ;

    static function init() {
        $test_ini = TestHelper::parseConfigFile( 'test' );

        if ( @$test_ini[ 'TEST_URL_BASE' ] != null ) {
            $GLOBALS[ 'TEST_URL_BASE' ] = $test_ini[ 'TEST_URL_BASE' ];
        } else {
            echo "** TEST_URL_BASE is not set, using localhost \n";
            $GLOBALS[ 'TEST_URL_BASE' ] = 'localhost';
        }

        TestHelper::$SCHEMA_HELPER = new SchemaCopy( $test_ini[ 'test' ] );
        TestHelper::$FIXTURES = new FixturesLoader();
    }

    /**
     * @throws Exception
     */
    static function resetDb() {
        TestHelper::$SCHEMA_HELPER->createDatabase() ;
        TestHelper::$SCHEMA_HELPER->prepareSchemaTables();
        TestHelper::$SCHEMA_HELPER->resetAllTables();
        TestHelper::$FIXTURES->loadFixtures();
    }

    public static function parseConfigFile( $env ) {
        return parse_ini_file(PROJECT_ROOT . '/inc/config.' . $env . '.ini', true);
    }
}

