<?php

/**
 * @covers WordCount_Counter::initializeJobWordCount
 * @group regression
 * User: dinies
 * Date: 30/06/16
 * Time: 10.52
 */
class InitializeJobWordCountTest extends AbstractTest
{

    
    /**
     * @group regression
     * @covers WordCount_Counter::initializeJobWordCount
     */
    function test_initializeJobWordCount()
    {
        $test_initializer= new UnitTestInitializer( Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE ));
        $job= $test_initializer->getJob();
        
        $wordCount= new WordCount_Counter();

        $result=$wordCount->initializeJobWordCount($job['id'],$job['password']);

        $this->assertTrue($result instanceof WordCount_Struct);
        $this->assertEquals($job['id'],$result->getIdJob());
        $this->assertEquals($job['password'],$result->getJobPassword());
        $this->assertEquals("34.00",$result->getNewWords());
        $this->assertEquals("0.00",$result->getDraftWords());
        $this->assertEquals("10.00",$result->getTranslatedWords());
        $this->assertEquals("0.00",$result->getApprovedWords());
        $this->assertEquals("0.00",$result->getRejectedWords());
        $this->assertNull($result->getIdSegment());
        $this->assertNull($result->getOldStatus());
        $this->assertNull($result->getNewStatus());
        $this->assertEquals(44,$result->getTotal());

    }
}