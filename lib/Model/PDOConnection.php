<?php
class PDOConnection {
	private static $instance;

    private $user ;
    private $server ;
    private $pass ;
    private $database ;

	private function __construct($server=null, $user=null, $pass=null, $database=null) {
		$this->server   = $server;
		$this->user     = $user;
		$this->pass     = $pass;
		$this->database = $database;

        $connString = "mysql:host=" . $server . ";dbname=" . $database ;
        $this->connection = new PDO($connString, $user, $pass);
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->connection->exec("set names utf8");
	}

	public static function obtain($server=null, $user=null, $pass=null, $database=null) {
		if (!self::$instance) {
			self::$instance = new PDOConnection($server, $user, $pass, $database);
		}
		return self::$instance;
	}

    public static function connectINIT() {
        return self::obtain(
            INIT::$DB_SERVER, INIT::$DB_USER,
            INIT::$DB_PASS, INIT::$DB_DATABASE
        );
    }

}
