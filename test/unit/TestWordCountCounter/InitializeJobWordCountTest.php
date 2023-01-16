<?php

/**
 * @covers WordCount_CounterModel::initializeJobWordCount
 * @group  regression
 * User: dinies
 * Date: 30/06/16
 * Time: 10.52
 */
class InitializeJobWordCountTest extends AbstractTest {
    protected $database_instance;


    /**
     * @group  regression
     * @covers WordCount_CounterModel::initializeJobWordCount
     */
    function test_initializeJobWordCount() {

        TestHelper::resetDb();

        $db = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $PDO = $db->getConnection();

        $sql = file( INIT::$ROOT . '/test/support/files/ProjectDump.sql' );

        foreach( $sql as $statement){
            try {
                $PDO->query( $statement );
            } catch( Exception $e ){
                print_r( $statement );
                print_r( $e->getTraceAsString() );
            }
        }

        $job       = new Jobs_JobStruct( $db->getConnection()->query( "SELECT * FROM jobs where id = 1 LIMIT 1" )->fetch() );
        $wordCount = new WordCount_CounterModel();

        $result = $wordCount->initializeJobWordCount( $job[ 'id' ], $job[ 'password' ] );

        $this->assertTrue( $result instanceof WordCount_Struct );
        $this->assertEquals( $job[ 'id' ], $result->getIdJob() );
        $this->assertEquals( $job[ 'password' ], $result->getJobPassword() );
        $this->assertEquals( "114.2", $result->getNewWords() );
        $this->assertEquals( "0.00", $result->getDraftWords() );
        $this->assertEquals( "0.00", $result->getTranslatedWords() );
        $this->assertEquals( "155", $result->getApprovedWords() );
        $this->assertEquals( "0.00", $result->getRejectedWords() );
        $this->assertNull( $result->getIdSegment() );
        $this->assertNull( $result->getOldStatus() );
        $this->assertNull( $result->getNewStatus() );
        $this->assertEquals( 269.2, $result->getTotal() );

    }
}