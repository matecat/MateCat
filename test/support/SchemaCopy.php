<?php

class SchemaCopy {
  public $conn ;
  public $dbConn ;

  function getDbConn($config) {
    $string = "mysql:host={$config['DB_SERVER']};dbname={$config['DB_DATABASE']};charset=UTF8";

    if ($this->dbConn == null ) {
      $this->dbConn = new PDO($string, $config['DB_USER'], $config['DB_PASS']);
      $this->dbConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    return $this->dbConn ;
  }

  function getConn($config) {
    $string = "mysql:host={$config['DB_SERVER']};charset=UTF8";

    if ($this->conn == null ) {
      $this->conn = new PDO($string, $config['DB_USER'], $config['DB_PASS']);
      $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    return $this->conn ;
  }

  public $config ;
  public function __construct($config){
    $this->config = $config;
  }

  function dropDatabase() {
    $conn = $this->getConn($this->config);
    $conn->query("DROP DATABASE IF EXISTS {$this->config['DB_DATABASE']}");
  }

  function createDatabase() {
    $conn = $this->getConn($this->config);
    $conn->query("CREATE DATABASE {$this->config['DB_DATABASE']}");
  }

  function useDatabase(){
    $conn = $this->getConn($this->config);
    $conn->query("USE {$this->config['DB_DATABASE']}");
  }

  function getDatabaseCreationStatement(){
    $sqlCreation = "CREATE DATABASE {$this->config['DB_DATABASE']};\n";
    $sqlCreation .= "USE {$this->config['DB_DATABASE']};\n\n";
    return $sqlCreation;
  }

  function getTables() {
    $conn = $this->getDbConn($this->config);
    $st =  $conn->query("SHOW FULL TABLES WHERE Table_type NOT LIKE 'VIEW' ");
    return $st->fetchAll();
  }

  function getTablesStatements() {
    $conn = $this->getDbConn($this->config);
    $result = array();
    foreach($this->getTables() as $k => $v) {
      $table_name = $v[ 0 ] ;
      $st = $conn->query( "SHOW CREATE TABLE $table_name ");
      array_push($result, $st->fetchAll());
    }

    return $result;
  }

  function execSql($sql) {
    $this->getDbConn($this->config)->query( $sql );
  }
}
