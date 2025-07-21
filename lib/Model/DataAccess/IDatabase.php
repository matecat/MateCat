<?php

namespace Model\DataAccess;
interface IDatabase {

    /**
     * Obtain an instance of the database
     *
     * @param string|null $server
     * @param string|null $user
     * @param string|null $password
     * @param string|null $database
     *
     * @return IDatabase
     */
    public static function obtain( string $server = null, string $user = null, string $password = null, string $database = null ): IDatabase;


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
    public function update( string $table, array $data, array $where = [ '1' => '0' ] ): int;


    /**
     * Run an insert query with an array as argument
     *
     * @param string $table Table to insert data in
     * @param array  $data  Data to insert, with the form (keyToUpdate => newValue)
     *
     * @return string
     */
    public function insert( string $table, array $data ): string;


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