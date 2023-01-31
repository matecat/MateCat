<?php

class TestHelper {
    /**
     * @var FixturesLoader
     */
    public $fixturesLoader;

    /**
     * @var SchemaCopy
     */
    public $schemaHelper;

    /**
     * TestHelper constructor.
     */
    public function __construct() {
        $test_ini = TestHelper::parseConfigFile( 'test' );

        if ( @$test_ini[ 'TEST_URL_BASE' ] != null ) {
            $GLOBALS[ 'TEST_URL_BASE' ] = $test_ini[ 'TEST_URL_BASE' ];
        } else {
            //echo "** TEST_URL_BASE is not set, using localhost \n"; // @TODO Throws Cannot modify header information - headers already sent by
            $GLOBALS[ 'TEST_URL_BASE' ] = 'localhost';
        }

        $this->schemaHelper = new SchemaCopy( $test_ini[ 'test' ] );
        $this->fixturesLoader = new FixturesLoader( $test_ini[ 'test' ] );
    }

    /**
     * @throws Exception
     */
    public function resetDb() {
        $this->schemaHelper->createDatabase() ;
        $this->schemaHelper->prepareSchemaTables();
        $this->schemaHelper->resetAllTables();
        $this->fixturesLoader->loadFixtures();
    }

    /**
     * @param $env
     * @return array|false
     */
    public function parseConfigFile( $env ) {
        return parse_ini_file(PROJECT_ROOT . '/inc/config.' . $env . '.ini', true);
    }
}

