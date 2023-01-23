<?php

/**
 * @group regression
 * @covers Database::begin
 * @covers Database::commit
 * @covers Database::rollback
 * User: dinies
 * Date: 12/04/16
 * Time: 17.15
 */
class BeginCommitRollbackTest extends AbstractTest
{

    protected $reflector;
    protected $property;
    /**
     * @var Database
     */
    protected $alfa_instance;

    /**
     * @var PDO
     */
    protected $beta_connection;
    protected $sql_create;
    protected $sql_read;
    protected $sql_insert_first_value;
    protected $sql_insert_second_value;
    protected $sql_drop;

    public function setUp()
    {
        parent::setUp();

        $copy_server = INIT::$DB_SERVER;
        $copy_user = INIT::$DB_USER;
        $copy_password =  INIT::$DB_PASS;
        $copy_database = INIT::$DB_DATABASE;
        $this->beta_connection = new PDO(
            "mysql:host={$copy_server};dbname={$copy_database};charset=UTF8",
            $copy_user,
            $copy_password,
            array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION // Raise exceptions on errors
            ));


        $this->reflectedClass = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->reflector = new ReflectionClass($this->reflectedClass);
        $this->property = $this->reflector->getProperty('instance');
        $this->reflectedClass->close();
        $this->property->setAccessible(true);
        $this->property->setValue($this->reflectedClass, null);
        $this->alfa_instance = $this->reflectedClass->obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);

        $this->sql_create = "CREATE TABLE Persons( PersonID INT)";
        $this->sql_drop="DROP TABLE Persons";
        $this->sql_insert_first_value = "INSERT INTO Persons VALUES (475144)";
        $this->sql_read = "SELECT * FROM Persons";
        $this->sql_insert_second_value = "INSERT INTO Persons VALUES (900341)";

        $this->alfa_instance->getConnection()->query($this->sql_create);
    }

    public function tearDown()
    {

        $this->alfa_instance->getConnection()->query($this->sql_drop);
        $this->reflectedClass = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
        $this->reflectedClass->close();
        startConnection();
        parent::tearDown();
    }

    /**
     * It checks that if the instance of alfa_connection
     * is in begin state, beta_connection won't be able
     * to read the current changes on the database
     * @group regression
     * @covers Database::begin
     */
    public function test_begin()
    {


        $this->alfa_instance->getConnection()->query($this->sql_insert_first_value);

        $alfa_view_before_begin_state = $this->alfa_instance->getConnection()->query($this->sql_read)->fetchAll(PDO::FETCH_ASSOC);

        $beta_view_before_begin_state = $this->beta_connection->query($this->sql_read)->fetchAll(PDO::FETCH_ASSOC);

        $this->alfa_instance->begin();
        $this->alfa_instance->getConnection()->query($this->sql_insert_second_value);

        $alfa_view_after_begin_state = $this->alfa_instance->getConnection()->query($this->sql_read)->fetchAll(PDO::FETCH_ASSOC);

        $beta_view_after_begin_state = $this->beta_connection->query($this->sql_read)->fetchAll(PDO::FETCH_ASSOC);

        $this->assertEquals($alfa_view_before_begin_state, $beta_view_before_begin_state);

        $this->assertNotEquals($alfa_view_after_begin_state, $beta_view_after_begin_state);
    }

    /**
     * It checks that if the instance of alfa_connection commit
     * the state of the database, beta_connection will be able
     * to read data updated;
     * @group regression
     * @covers Database::commit
     */
    public function test_commit()
    {

        $this->alfa_instance->getConnection()->query($this->sql_insert_first_value);

        $this->alfa_instance->begin();
        $this->alfa_instance->getConnection()->query($this->sql_insert_second_value);

        $alfa_view_before_commit_state = $this->alfa_instance->getConnection()->query($this->sql_read)->fetchAll(PDO::FETCH_ASSOC);

        $beta_view_before_commit_state = $this->beta_connection->query($this->sql_read)->fetchAll(PDO::FETCH_ASSOC);


        $this->alfa_instance->commit();


        $alfa_view_after_commit_state = $this->alfa_instance->getConnection()->query($this->sql_read)->fetchAll(PDO::FETCH_ASSOC);

        $beta_view_after_commit_state = $this->beta_connection->query($this->sql_read)->fetchAll(PDO::FETCH_ASSOC);

        $this->assertNotEquals($alfa_view_before_commit_state, $beta_view_before_commit_state);

        $this->assertEquals($alfa_view_after_commit_state, $beta_view_after_commit_state);
    }
    /**
     * It checks that if the instance of alfa_connection invoke  rollback,
     * the state of the database will return to the original state just after the begin.
     * to read data updated;
     * @group regression
     * @covers Database::rollback
     */
    public function test_rollback()
    {

        $this->alfa_instance->getConnection()->query($this->sql_insert_first_value);

        $alfa_view_origin_state = $this->alfa_instance->getConnection()->query($this->sql_read)->fetchAll(PDO::FETCH_ASSOC);

        $this->alfa_instance->begin();
        $this->alfa_instance->getConnection()->query($this->sql_insert_second_value);

        $alfa_view_before_rollback_state = $this->alfa_instance->getConnection()->query($this->sql_read)->fetchAll(PDO::FETCH_ASSOC);

        $beta_view_before_rollback_state = $this->beta_connection->query($this->sql_read)->fetchAll(PDO::FETCH_ASSOC);


        $this->alfa_instance->rollback();


        $alfa_view_after_rollback_state = $this->alfa_instance->getConnection()->query($this->sql_read)->fetchAll(PDO::FETCH_ASSOC);

        $beta_view_after_rollback_state = $this->beta_connection->query($this->sql_read)->fetchAll(PDO::FETCH_ASSOC);

        $this->assertNotEquals($alfa_view_before_rollback_state, $beta_view_before_rollback_state);

        $this->assertEquals($alfa_view_after_rollback_state, $beta_view_after_rollback_state);

        $this->assertEquals($alfa_view_origin_state,$alfa_view_after_rollback_state);
    }
}