<?php

interface IDatabase {

    /**
     * Obtain an instance of the database
     * @param string $server
     * @param string $user
     * @param string $password
     * @param string $database
     * @return IDatabase
     */
    public static function obtain($server=null, $user=null, $password=null, $database=null);


    /**
     * Connect and select database
     */
    public function connect();

    /**
     * CLose the connection
     */
    public function close();


    /**
     * Swith the DB
     * @param $name string name of the db to connect to
     */
    public function useDb($name);


    /**
     * Begin a transaction for InnoDB tables
     */
    public function begin();


    /**
     * Commit a transaction for InnoDB tables
     */
    public function commit();


    /**
     * Rollback a transaction for InnoDB tables
     */
    public function rollback();


    /**
     * Executes SQL query to an open connection
     * @param string $sql Query to execute
     * @return PDOStatement Query result
     */
    public function query($sql);


    /**
     * Perform a query, fetching only the first row and freeing the result
     * @param $query string Query to execute
     * @return mixed First fetched row
     */
    public function query_first($query);


    /**
     * Perform a query and return all the results
     * @param $query string Query to run
     * @return array All the fetched results
     */
    public function fetch_array($query);


    /**
     * Execute a update query with an array as argument
     * @param string $table Table to update
     * @param array $data Data to update, with the form (keyToUpdate => newValue)
     * @param string $where Condition
     * @return integer Number of affected rows
     */
    public function update($table, $data, $where='1');


    /**
     * Run an insert query with an array as argument
     * @param string $table Table to insert data in
     * @param array $data Data to insert, with the form (keyToUpdate => newValue)
     * @return string
     */
    public function insert($table, $data);


    /**
     * Get the ID of the last inserted row
     * @return mixed Last insert ID
     */
    public function last_insert();


    /**
     * Sanitize a input string
     * This function is not required with the PDO extension. However, it's added for legacy support.
     * and it may not work as expected.
     * @param string $string String to clean
     * @return string Sanitized string
     */
    public function escape($string);

}