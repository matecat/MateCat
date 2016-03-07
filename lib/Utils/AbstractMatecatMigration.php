<?php


require INIT::$ROOT . '/test/support/SchemaCopy.php';
require INIT::$ROOT . '/test/support/SeedLoader.php';

class AbstractMatecatMigration extends \Phinx\Migration\AbstractMigration {

    public $sql_up;
    public $sql_down;

    public function up() {
        $this->execute($this->sql_up);
        $this->resetSchemaDump('up');
    }

    public function down() {
        $this->execute($this->sql_down);
        $this->resetSchemaDump('down');
    }

    private function resetSchemaDump($direction) {

        $config_ini = parse_ini_file(INIT::$ROOT . '/inc/config.ini', true);
        $current_env = $config_ini['ENV'];
        $database = new SchemaCopy($config_ini[$current_env]);
        $statements = $database->getTablesStatements();

        $sql = $database->getDatabaseCreationStatement();
        foreach($statements as $key => $statement ) {
            $sql .= preg_replace('/AUTO_INCREMENT=\d+/i', '', $statement[0]['Create Table']);
            $sql .= "; \n\n";
        }

        $seedLoader = new SeedLoader( $config_ini[ $current_env ]);
        $sql .= $seedLoader->getSeedSql();


        $pdo = Database::obtain()->getConnection();

        $phinx_records = $pdo->query("select * from phinxlog");

        foreach($phinx_records as $record) {
            if ( $direction == 'down' && $record['version'] == $this->version ) {
                continue;
            }

            $sql .= "\nINSERT INTO `phinxlog` ( version, start_time, end_time ) VALUES (" .
              " '${record['version']}', '${record['start_time']}', '${record['end_time']}');";
        }

        if ( $direction == 'up' ) {
            $sql .= "\nINSERT INTO `phinxlog` ( version, start_time, end_time ) VALUES (" .
                    " '$this->version', '" . date('c') ."', '" . date('c') . "');";
        }

        $sql .= $seedLoader->getConversionLogSchema();

        file_put_contents( INIT::$ROOT . '/lib/Model/matecat.sql', $sql );
    }

}
