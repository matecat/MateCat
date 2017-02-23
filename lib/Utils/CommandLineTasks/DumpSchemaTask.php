<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 22/02/2017
 * Time: 17:05
 */

namespace CommandLineTasks;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DumpSchemaTask extends Command {

    protected function configure() {
        $this
                ->setName( 'db:schema:dump' )
                ->setDescription( 'Rebuilds matecat.sql schema file.' )
                ->setHelp( "Rebuilds matecat.sql schema file" );
    }

    protected function execute( InputInterface $input, OutputInterface $output ) {
        $config_ini = parse_ini_file(\INIT::$ROOT . '/inc/config.ini', true);
        $current_env = $config_ini['ENV'];
        $database = new \SchemaCopy($config_ini[$current_env]);
        $database->saveSchemaFile();
    }
}