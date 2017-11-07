<?php

/**
 * Class which implements a database using PDO
 *
 * The used test script can be found at: https://gist.github.com/reneses/3108444332d4e56c0b73
 */
class Database implements IDatabase {

    /**
     * Unique instance of the class (singleton design pattern)
     * @var Database $instance
     */
    private static $instance;

    /**
     * Established connection
     * @var PDO $connection
     */
    private $connection;

    // Connection variables
    private $server = ""; //database server
    private $user = ""; //database login name
    private $password = ""; //database login password
    private $database = ""; //database name

    // Affected rows TODO: remove, it's not thread safe. Just kept for legacy support
    public $affected_rows;


    const SEQ_ID_SEGMENT = 'id_segment';
    const SEQ_ID_PROJECT = 'id_project';
    const SEQ_ID_DQF_PROJECT = 'id_dqf_project' ;

    protected static $SEQUENCES = [
            Database::SEQ_ID_SEGMENT,
            Database::SEQ_ID_PROJECT,
            Database::SEQ_ID_DQF_PROJECT
    ];

    /**
     * Instantiate the database (singleton design pattern)
     * @param string $server
     * @param string $user
     * @param string $password
     * @param string $database
     */
    private function __construct($server=null, $user=null, $password=null, $database=null) {

        // Check that the variables are not empty
        if ($server == null || $user == null || $database == null) {
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
        if (!self::$instance  ||  $server != null  &&  $user != null  &&  $password != null  &&  $database != null) {
            self::$instance = new Database($server, $user, $password, $database);
        }
        return self::$instance;
    }

    /**
     * Class destructor
     */
    public function __destruct(){
        $this->close();
    }

    /**
     * @return PDO
     */
    public function getConnection() {
        if ( empty( $this->connection ) || !$this->connection instanceof PDO ) {
            $this->connection = new PDO(
                    "mysql:host={$this->server};dbname={$this->database}",
                    $this->user,
                    $this->password,
                    array(
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Raise exceptions on errors
                            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                    ) );
            $this->connection->exec( "SET names utf8" );
        }
        return $this->connection;
    }

    public function connect() {
        $this->getConnection();
    }

    /**
     * @return bool
     * @throws PDOException
     */
    public function ping(){
        $this->getConnection()->query( "SELECT 1 FROM DUAL" );
        return true;
    }

    /**
     * @Override
     * {@inheritdoc}
     */
    public function close() {
        $this->connection = null;
    }

    public function reconnect() {
      $this->close();
      $this->getConnection();
    }


    /**
     * @Override
     * {@inheritdoc}
     */
    public function useDb($name){
        $this->getConnection()->query("USE ".$name);
        $this->database = $name;
    }


    /**
     * @Override
     * {@inheritdoc}
     */
    public function begin() {
        if ( ! $this->getConnection()->inTransaction() ) {
            $this->getConnection()->beginTransaction();
        }
        return $this->getConnection();
    }


    /**
     * @Override
     * {@inheritdoc}
     */
    public function commit() {
        $this->getConnection()->commit();
    }


    /**
     * @Override
     * {@inheritdoc}
     */
    public function rollback() {
        $this->getConnection()->rollBack();
    }


    /**
     * @Override
     * {@inheritdoc}
     */
    public function query($sql) {
        $result = $this->getConnection()->query($sql);
        $this->affected_rows = $result->rowCount();
        return $result;
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
     * @deprecated
     * @Override
     * {@inheritdoc}
     * @deprecated
     * TODO: Re-implement with prepared statement
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
    public function update( $table, $data, $where = '1' ) {

        // Prepare the statement
        $valuesToBind = [];
        $query        = "UPDATE $table SET ";
        $currentIndex = 0;

        foreach ( $data as $key => $value ) {
            $query                                   .= "$key = :value{$currentIndex}, ";
            $valuesToBind[ ":value{$currentIndex}" ] = $value;
            ++$currentIndex;
        }

        $query             = rtrim( $query, ', ' );
        $query             .= " WHERE $where;";
        $preparedStatement = $this->getConnection()->prepare( $query );

        // Execute it
        $preparedStatement->execute( $valuesToBind );
        $affected            = $preparedStatement->rowCount();
        $this->affected_rows = $affected;

        return $affected;
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
        $preparedStatement = $this->getConnection()->prepare($query);

        // Execute it
        $preparedStatement->execute($valuesToBind);
        $this->affected_rows = $preparedStatement->rowCount();
        return $this->last_insert();
    }


    /**
     * @Override
     * {@inheritdoc}
     */
    public function last_insert() {
        return $this->getConnection()->lastInsertId();
    }


    /**
     * TODO this trim should be removed and ALL codebase migrated from $db->escape() to prepared Statements
     * @Override
     * {@inheritdoc}
     */
    public function escape( $string ) {
        return substr( $this->getConnection()->quote( $string ), 1, -1 );
    }

    /**
     * @param string $sequence_name
     * @param int    $seqIncrement
     *
     * @return array
     */
    public function nextSequence( $sequence_name, $seqIncrement = 1 ){

        if( array_search( $sequence_name, static::$SEQUENCES ) === false ){
            throw new \PDOException( "Undefined sequence " . $sequence_name );
        }

        $this->getConnection()->beginTransaction();

        $statement = $this->getConnection()->prepare( "SELECT " . $sequence_name . " FROM sequences FOR UPDATE;" );
        $statement->execute();
        $first_id = $statement->fetch( PDO::FETCH_OBJ );

        $statement = $this->getConnection()->prepare( "UPDATE sequences SET " . $sequence_name . " = " . $sequence_name . " + :seqIncrement where 1 limit 1;" );
        $statement->bindValue( ':seqIncrement', $seqIncrement, PDO::PARAM_INT );
        $statement->execute();

        $this->getConnection()->commit();

        return range( $first_id->{$sequence_name}, $first_id->{$sequence_name} + $seqIncrement -1 );

    }

}
