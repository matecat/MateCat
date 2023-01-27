<?php

use Url\JobUrlStruct;

class JobUrlBuilderTest extends AbstractTest {

    /**
     * @var Database
     */
    protected $database_instance;

    public function setUp() {
        parent::setUp();

        $test_ini = TestHelper::parseConfigFile( 'test' );

        $db_server = (isset($test_ini[ 'test' ])) ? $test_ini[ 'test' ]['DB_SERVER'] : INIT::$DB_SERVER;
        $db_user = (isset($test_ini[ 'test' ])) ? $test_ini[ 'test' ]['DB_USER'] : INIT::$DB_USER;
        $db_pass = (isset($test_ini[ 'test' ])) ? $test_ini[ 'test' ]['DB_PASS'] : INIT::$DB_PASS;
        $db_database = (isset($test_ini[ 'test' ])) ? $test_ini[ 'test' ]['DB_DATABASE'] : INIT::$DB_DATABASE;

        $this->database_instance = Database::obtain( $db_server, $db_user, $db_pass, $db_database );
    }

    public function testBuildTranslationUrl(){
        $job = new Jobs_JobStruct( $this->database_instance->getConnection()->query( "SELECT * FROM jobs ORDER BY id DESC LIMIT 1" )->fetch() );
        $jobUrlStruct = \Url\JobUrlBuilder::createFromJobStruct($job, [
            'id_segment' => $job->job_first_segment
        ]);

        $this->assertInstanceOf(JobUrlStruct::class, $jobUrlStruct);
        $this->assertNotNull($jobUrlStruct->getTranslationUrl());
        $this->assertEquals($jobUrlStruct->getUrlByRevisionNumber(), $jobUrlStruct->getTranslationUrl());
        $this->assertNotNull($jobUrlStruct->getReviseUrl());
        $this->assertNull($jobUrlStruct->getRevise2Url());
        $this->assertNotNull($jobUrlStruct->getUrlByRevisionNumber(1));
        $this->assertNull($jobUrlStruct->getUrlByRevisionNumber(2));
        $this->assertNotNull($jobUrlStruct->getUrlByRevisionNumber("1"));
        $this->assertNull($jobUrlStruct->getUrlByRevisionNumber("2"));
        $this->assertTrue($jobUrlStruct->hasReview());
        $this->assertFalse($jobUrlStruct->hasSecondPassReview());
    }
}