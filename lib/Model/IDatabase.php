<?php

interface IDatabase {

    /**
     * Obtain an instance of the database
     *
     * @param string $server
     * @param string $user
     * @param string $password
     * @param string $database
     *
     * @return IDatabase
     */
    public static function obtain( $server = null, $user = null, $password = null, $database = null );


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
     *
     * @param $name string name of the db to connect to
     */
    public function useDb( $name );


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
     * Execute a update query with an array as argument
     *
     * @param string $table Table to update
     * @param array  $data  Data to update, with the form (keyToUpdate => newValue)
     * @param array  $where Condition
     *
     * @return integer Number of affected rows
     */
    public function update( $table, $data, array $where = [ '1' => '0' ] );


    /**
     * Run an insert query with an array as argument
     *
     * @param string $table Table to insert data in
     * @param array  $data  Data to insert, with the form (keyToUpdate => newValue)
     *
     * @return string
     */
    public function insert( $table, array $data );


    /**
     * Get the ID of the last inserted row
     * @return mixed Last insert ID
     */
    public function last_insert();


    /**
     * Sanitize a input string
     * This function is not required with the PDO extension. However, it's added for legacy support.
     * and it may not work as expected.
     *
     * @param string $string String to clean
     *
     * @return string Sanitized string
     */
    public function escape( $string );

}