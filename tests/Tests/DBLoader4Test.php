<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 18/02/14
 * Time: 19.14
 *
 */

include_once("AbstractTest.php");

class Tests_DBLoader4Test {

    public static $DB_SERVER   = "localhost"; //database server
    public static $DB_DATABASE = "unittest_matecat_local"; //database name
    public static $DB_USER     = "unt_matecat_user"; //database login
    public static $DB_PASS     = "unt_matecat_user"; //databasepassword

    public static $db;

    public static function resetDB(){
        $cmd = "mysql -u root < " . dirname( __FILE__ ) . "/unitTest_matecat_local.sql 2>&1";
        $res = shell_exec( $cmd );
        if( !is_null($res) ){
            $msg = 'Shell Exec Command Failed: ' . $cmd;
            throw new RuntimeException( $msg );
        }
        echo "." . str_pad( "Database Reset Done" , 40, " ", STR_PAD_LEFT ) . "\n";
        self::getUp();
    }

    public static function getUp(){
        if( self::$db == null ){
            self::$db = Database::obtain ( self::$DB_SERVER, self::$DB_USER, self::$DB_PASS, self::$DB_DATABASE );
            self::$db->connect ();
        }
        return self::$db;
    }

}