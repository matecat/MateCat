<?php

use Model\DataAccess\Database;
use TestHelpers\AbstractTest;
use Utils\Registry\AppConfig;


/**
 * This test is meant to test transaction isolation between two connected instances of the Database class
 *
 * @group  regression
 * @covers Database::begin
 * @covers \Model\DataAccess\Database::commit
 * @covers \Model\DataAccess\Database::rollback
 * User: dinies
 * Date: 12/04/16
 * Time: 17.15
 */
class BeginCommitRollbackTest extends AbstractTest {

    protected $reflector;
    protected $property;
    /**
     * @var Database
     */
    protected $client_1_instance;

    /**
     * @var PDO
     */
    protected $raw_client_instance;
    protected $sql_create;
    protected $sql_read;
    protected $sql_insert_first_value;
    protected $sql_insert_second_value;
    protected $sql_drop;

    public function setUp(): void {
        parent::setUp();

        $this->raw_client_instance = new PDO(
                "mysql:host=" . AppConfig::$DB_SERVER . ";dbname=" . AppConfig::$DB_DATABASE . ";charset=UTF8",
                AppConfig::$DB_USER,
                AppConfig::$DB_PASS,
                [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION // Raise exceptions on errors
                ]
        );

        $this->client_1_instance = Database::obtain( AppConfig::$DB_SERVER, AppConfig::$DB_USER, AppConfig::$DB_PASS, AppConfig::$DB_DATABASE );

        $this->sql_create = "CREATE TABLE Persons( PersonID INT)";
        $this->sql_drop   = "DROP TABLE Persons";
        $this->sql_read   = "SELECT * FROM Persons";

        $this->sql_insert_first_value  = "INSERT INTO Persons VALUES (475144)";
        $this->sql_insert_second_value = "INSERT INTO Persons VALUES (900341)";

        $this->raw_client_instance->query( $this->sql_create );
    }

    public function tearDown(): void {
        $this->raw_client_instance->query( $this->sql_drop );
        parent::tearDown();
    }

    /**
     * It checks that if the instance of client_1_instance
     * is in begin state, raw_client_instance won't be able
     * to read the current changes on the database.
     *
     * Moreover
     * @group  regression
     * @covers \Model\DataAccess\Database::begin
     */
    public function test_begin_and_rollback() {


        $this->client_1_instance->getConnection()->query( $this->sql_insert_first_value );

        $client_1_view_before_begin_state = $this->client_1_instance->getConnection()->query( $this->sql_read )->fetchAll( PDO::FETCH_ASSOC );
        $raw_view_before_begin_state      = $this->raw_client_instance->query( $this->sql_read )->fetchAll( PDO::FETCH_ASSOC );

        $this->assertEquals( $client_1_view_before_begin_state, $raw_view_before_begin_state );

        // test transaction
        $this->client_1_instance->begin();
        $this->client_1_instance->getConnection()->query( $this->sql_insert_second_value );

        $client_1_view_after_begin_state = $this->client_1_instance->getConnection()->query( $this->sql_read )->fetchAll( PDO::FETCH_ASSOC );
        $raw_view_after_begin_state      = $this->raw_client_instance->query( $this->sql_read )->fetchAll( PDO::FETCH_ASSOC );

        $this->assertNotEquals( $client_1_view_after_begin_state, $raw_view_after_begin_state );

        $this->client_1_instance->rollback();

        $client_1_view_after_rollback_state = $this->client_1_instance->getConnection()->query( $this->sql_read )->fetchAll( PDO::FETCH_ASSOC );
        $raw_view_after_rollback_state      = $this->raw_client_instance->query( $this->sql_read )->fetchAll( PDO::FETCH_ASSOC );

        $this->assertEquals( $client_1_view_after_rollback_state, $raw_view_after_rollback_state );

    }

    /**
     * It checks that if the instance of client_1_instance commit
     * the state of the database, raw_client_instance will be able
     * to read data updated;
     * @group  regression
     * @covers \Model\DataAccess\Database::commit
     */
    public function test_commit() {

        $this->client_1_instance->getConnection()->query( $this->sql_insert_first_value );

        $this->client_1_instance->begin();
        $this->client_1_instance->getConnection()->query( $this->sql_insert_second_value );

        $alfa_view_before_commit_state = $this->client_1_instance->getConnection()->query( $this->sql_read )->fetchAll( PDO::FETCH_ASSOC );
        $beta_view_before_commit_state = $this->raw_client_instance->query( $this->sql_read )->fetchAll( PDO::FETCH_ASSOC );
        $this->assertNotEquals( $alfa_view_before_commit_state, $beta_view_before_commit_state );

        $this->client_1_instance->commit();

        $alfa_view_after_commit_state = $this->client_1_instance->getConnection()->query( $this->sql_read )->fetchAll( PDO::FETCH_ASSOC );
        $beta_view_after_commit_state = $this->raw_client_instance->query( $this->sql_read )->fetchAll( PDO::FETCH_ASSOC );

        $this->assertEquals( $alfa_view_after_commit_state, $beta_view_after_commit_state );

    }

}