<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/12/2016
 * Time: 10:47
 */

namespace CommandLineTasks\Test;


use Exception;
use SchemaCopy;
use SeedLoader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PrepareDatabaseTask extends Command
{
    protected function configure() {
        $this
            ->setName('test:prepare-database')
            ->setDescription('Copies development database schema to test database')
            ->setHelp('Copies development database schema to test database');
    }

    public function execute( InputInterface $input, OutputInterface $output ) {

        $dev_ini = \TestHelper::parseConfigFile('development') ;
        $test_ini = \TestHelper::parseConfigFile('test');

        if ( $dev_ini['ENV'] != 'development' ) {
            throw new Exception('Source config must be development');
        }

        if ( $test_ini['ENV'] != 'test') {
            throw new Exception('Destination config must be test');
        }

        $testDatabase = new SchemaCopy( $test_ini[ 'test' ] );
        $devDatabase = new SchemaCopy( $dev_ini[ 'development' ] );

        $this->prepareTestSchema($testDatabase, $devDatabase);
        $this->loadSeedData($testDatabase);

    }

    /**
     * @param $database
     */
    protected function loadSeedData( $database ) {
        $seeder = new SeedLoader( $database );
        $seeder->loadEngines();
    }

    protected function prepareTestSchema(SchemaCopy $testDatabase, SchemaCopy $devDatabase) {
        $testDatabase->dropDatabase();
        $testDatabase->createDatabase();

        $tables = $devDatabase->getTablesStatements();

        foreach($tables as $k => $statement) {
            $testDatabase->execSql($statement);
        }

        $testDatabase->resetAllTables();

    }

}