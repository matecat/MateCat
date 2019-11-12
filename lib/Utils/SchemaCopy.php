<?php

class SchemaCopy {
    public $conn;
    public $dbConn;

    function getDbConn( $config ) {
        $string = "mysql:host={$config['DB_SERVER']};dbname={$config['DB_DATABASE']};charset=UTF8";

        if ( $this->dbConn == null ) {
            $this->dbConn = new PDO( $string, $config[ 'DB_USER' ], $config[ 'DB_PASS' ] );
            $this->dbConn->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
        }

        return $this->dbConn;
    }

    function getConn( $config ) {
        $string = "mysql:host={$config['DB_SERVER']};charset=UTF8";

        if ( $this->conn == null ) {
            $this->conn = new PDO( $string, $config[ 'DB_USER' ], $config[ 'DB_PASS' ] );
            $this->conn->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
        }

        return $this->conn;
    }

    public $config;

    public function __construct( $config ) {
        $this->config = $config;
    }

    function dropDatabase() {
        $conn = $this->getConn( $this->config );
        $conn->query( "DROP DATABASE IF EXISTS {$this->config['DB_DATABASE']}" );
    }

    function createDatabase() {
        $conn = $this->getConn( $this->config );
        $conn->query( "CREATE DATABASE IF NOT EXISTS {$this->config['DB_DATABASE']}" );
    }

    function useDatabase() {
        $conn = $this->getConn( $this->config );
        $conn->query( "USE {$this->config['DB_DATABASE']}" );
    }

    function getDatabaseCreationStatement() {
        $sqlCreation = "CREATE DATABASE {$this->config['DB_DATABASE']};\n";
        $sqlCreation .= "USE {$this->config['DB_DATABASE']};\n\n";

        return $sqlCreation;
    }

    function getTables() {
        $conn = $this->getDbConn( $this->config );
        $st   = $conn->query( "SHOW FULL TABLES WHERE Table_type NOT LIKE 'VIEW' " );

        return $st->fetchAll();
    }

    function getTablesStatements() {
        $conn   = $this->getDbConn( $this->config );
        $result = [];
        foreach ( $this->getTables() as $k => $v ) {
            $table_name = $v[ 0 ];
            $st         = $conn->query( "SHOW CREATE TABLE $table_name " );

            array_push( $result, static::removePartitionInfo( $st->fetch()[ 'Create Table' ] ) );
        }

        return $result;
    }

    /**
     * Partition info slow down database reset during test runs.
     */
    static function removePartitionInfo( $string ) {
        return preg_replace( '/\/\*(.+)\*\//s', '', $string );
    }

    function resetAllTables() {
        $conn = $this->getDbConn( $this->config );
        foreach ( $this->getTables() as $k => $v ) {
            $table_name = $v[ 0 ];
            // $conn->query( "TRUNCATE TABLE $table_name ");
            $conn->query( "DELETE FROM $table_name " ); // DELETE seems to be faster than truncate
            $conn->query( "ALTER TABLE $table_name AUTO_INCREMENT = 1" );
        }

        $conn->query( "INSERT INTO `engines` VALUES (10,'NONE','NONE','No MT','','',NULL,NULL,NULL,'{}','NONE','',NULL,100,0,NULL);" );
        $conn->query( 'INSERT INTO `engines` VALUES (11,\'MyMemory (All Pairs)\',\'TM\',\'Machine translation from Google Translate and Microsoft Translator.\',\'http://api.mymemory.translated.net\',\'get\',\'set\',\'update\',\'delete\', \'{\"gloss_get_relative_url\":\"glossary/get\",\"gloss_set_relative_url\":\"glossary/set\",\"gloss_update_relative_url\":\"glossary/update\",\"glossary_import_relative_url\":\"glossary/import\",\"glossary_export_relative_url\":\"glossary/export\",\"gloss_delete_relative_url\":\"glossary/delete\",\"tmx_import_relative_url\":\"tmx/import\",\"tmx_status_relative_url\":\"tmx/status\",\"tmx_export_create_url\":\"tmx/export/create\",\"tmx_export_check_url\":\"tmx/export/check\",\"tmx_export_download_url\":\"tmx/export/download\",\"tmx_export_list_url\":\"tmx/export/list\",\"tmx_export_email_url\":\"tmx/export/create\",\"api_key_create_user_url\":\"createranduser\",\"api_key_check_auth_url\":\"authkey\",\"analyze_url\":\"analyze\",\"detect_language_url\":\"langdetect.php\"}\',\'MyMemory\',\'{}\',\'1\',0,1,NULL);' );
        $conn->query( 'UPDATE engines SET id = 0 WHERE id = 10 ;' );
        $conn->query( 'UPDATE engines SET id = 1 WHERE id = 11 ;' );
        $conn->query( 'INSERT INTO sequences ( id_segment, id_project, id_dqf_project ) VALUES ( IFNULL( (SELECT MAX(id) + 1 FROM segments), 1), IFNULL( (SELECT MAX(id) + 1 FROM projects), 1), 1 );' );

    }

    function prepareSchemaTables() {

        if ( !empty( $this->getTables() ) ) {
            return;
        }

        $sql_tables = file_get_contents( INIT::$ROOT . "/lib/Model/matecat.sql" );
        $sql_tables = str_replace( "`matecat`", "`unittest_matecat_local`", $sql_tables );
        file_put_contents( '/tmp/_test_sql_create_database.sql', $sql_tables );

        $cmd = "mysql -u admin -padmin -h {$this->config[ 'DB_SERVER' ]} < /tmp/_test_sql_create_database.sql 2>&1";
        $res = shell_exec( $cmd );
        if ( !is_null( $res ) && stripos( $res, 'warning' ) === false ) {
            $msg = 'Shell Exec Command Failed: ' . $cmd;
            throw new RuntimeException( $msg );
        }
        echo "." . str_pad( "Database Reset Done", 40, " ", STR_PAD_LEFT ) . "\n";

    }

    function execSql( $sql ) {
        $this->getDbConn( $this->config )->query( $sql );
    }
}
