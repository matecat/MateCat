<?php

class Database implements IDatabase {

    /**
     * @var IDatabase   $instance
     * @var string      $server
     * @var string      $user
     * @var string      $password
     * @var string      $database
     * @var PDO         $connection
     */
    // Store the single instance of Database
	private static $instance;

	// Connection variables
	private $server = ""; //database server
	private $user = ""; //database login name
	private $password = ""; //database login password
	private $database = ""; //database name

	// Connection
	private $connection;


    /**
     * Instantiate the database (singleton design pattern)
     * @param string $server
     * @param string $user
     * @param string $password
     * @param string $database
     */
	private function __construct($server=null, $user=null, $password=null, $database=null) {

		// Check that the variables are not empty
		if ($server == null || $user == null || $database == null) { // TODO: pass?
			throw new InvalidArgumentException("Database information must be passed in when the object is first created.");
		}

		// Set fields
		$this->server = $server;
		$this->user = $user;
		$this->password = $password;
		$this->database = $database;
	}


    /**
     * @Override
     * {@inheritdoc}
     */
	public static function obtain($server=null, $user=null, $password=null, $database=null) {
		if (!self::$instance) {
			self::$instance = new PDODatabase($server, $user, $password, $database);
		}
		return self::$instance;
	}


    /**
     * @Override
     * {@inheritdoc}
     */
	public function connect() {
        $this->connection = new PDO(
			"mysql:host={$this->server};dbname={$this->database};charset=UTF8",
			$this->user,
			$this->password,
            array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION // Raise exceptions on errors
            ));
	}


    /**
     * @Override
     * {@inheritdoc}
     */
	public function close() {
		$this->connection = null;
	}


    /**
     * @Override
     * {@inheritdoc}
     */
	public function useDb($name){
		$preparedStatement = $this->connection->prepare("USE :databaseName");
        $preparedStatement->execute(array(':databaseName'=>$name));
		$this->database = $name;
	}


    /**
     * @Override
     * {@inheritdoc}
     */
	public function begin() {
		$this->connection->beginTransaction();
	}


    /**
     * @Override
     * {@inheritdoc}
     */
	public function commit() {
		$this->connection->commit();
	}


    /**
     * @Override
     * {@inheritdoc}
     */
	public function rollback() {
		$this->connection->rollBack();
	}


    /**
     * @Override
     * {@inheritdoc}
     */
	public function query($sql) {
		return $this->connection->query($sql);
	}


    /**
     * @Override
     * {@inheritdoc}
     */
    public function query_first($query) {
        $result = $this->query($query);
        $out = $result->fetch(PDO::FETCH_ASSOC);
        $result->closeCursor();
        return $out;
    }


    /**
     * @Override
     * {@inheritdoc}
     */
    public function fetch_array($query) {
        $result = $this->query($query);
        $out = $result->fetchAll(PDO::FETCH_ASSOC);
        $result->closeCursor();
        return $out;
    }


    /**
     * @Override
     * {@inheritdoc}
     */
    public function update($table, $data, $where='1') {

        // Prepare the statement
        $valuesToBind = array();
        $query = "UPDATE $table SET ";
        $currentIndex = 0;
        foreach($data as $key => $value) {
            $query.= "$key = :value{$currentIndex}, ";
            $valuesToBind[":value{$currentIndex}"] = $value;
            ++$currentIndex;
        }
        $query = rtrim($query,', ');
        $query .= " WHERE $where;";
        $preparedStatement = $this->connection->prepare($query);

        // Execute it
        return $preparedStatement->execute($valuesToBind);
    }


    /**
     * @Override
     * {@inheritdoc}
     */
    public function insert($table, $data) {

        // Prepare the statement
        $valuesToBind = array();
        $keys = "";
        $values = "";
        $currentIndex = 0;
        foreach($data as $key => $value) {
            $keys .= "$key, ";
            $values .= ":value{$currentIndex}, ";
            $valuesToBind[":value{$currentIndex}"] = $value;
            ++$currentIndex;
        }
        $keys = rtrim($keys,', ');
        $values = rtrim($values,', ');
        $query = "INSERT INTO $table ($keys) VALUES ($values);";
        $preparedStatement = $this->connection->prepare($query);

        // Execute it
        $preparedStatement->execute($valuesToBind);
        return $this->connection->lastInsertId();
    }


    /**
     * @Override
     * {@inheritdoc}
     */
    public function last_insert() {
        $result = $this->connection->query("SELECT LAST_INSERT_ID() as last");
        $out = $result->fetch(PDO::FETCH_ASSOC);
        return $out['last'];
    }


    /**
     * @Override
     * {@inheritdoc}
     */
    public function escape($string) {
        return $this->connection->quote($string);
    }

}