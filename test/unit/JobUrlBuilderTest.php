<?php

class JobUrlBuilderTest extends AbstractTest {

    /**
     * @var Database
     */
    protected $database_instance;

    public function setUp() {
        parent::setUp();

        $this->database_instance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
    }

    public function testBuildTranslationUrl(){
        $job = new Jobs_JobStruct( $this->database_instance->getConnection()->query( "SELECT * FROM jobs ORDER BY id DESC LIMIT 1" )->fetch() );
        $jobUrlStruct = \Url\JobUrlBuilder::createFromJobStruct($job, [
            'id_segment' => 1
        ]);

        $this->assertInstanceOf(\Url\JobUrlStruct::class, $jobUrlStruct);
        $this->assertNotNull($jobUrlStruct->getTranslationUrl());
        $this->assertEquals($jobUrlStruct->getUrlByRevisionNumber(), $jobUrlStruct->getTranslationUrl());
        $this->assertNull($jobUrlStruct->getReviseUrl());
        $this->assertNull($jobUrlStruct->getRevise2Url());
        $this->assertNull($jobUrlStruct->getUrlByRevisionNumber(1));
        $this->assertNull($jobUrlStruct->getUrlByRevisionNumber(2));
        $this->assertNull($jobUrlStruct->getUrlByRevisionNumber("1"));
        $this->assertNull($jobUrlStruct->getUrlByRevisionNumber("2"));
        $this->assertFalse($jobUrlStruct->hasReview());
        $this->assertFalse($jobUrlStruct->hasSecondPassReview());
    }

    public function testBuildTranslationUrlFromCredentials(){
        $job = new Jobs_JobStruct( $this->database_instance->getConnection()->query( "SELECT * FROM jobs ORDER BY id DESC LIMIT 1" )->fetch() );
        $jobUrlStruct = \Url\JobUrlBuilder::createFromCredentials($job->id, $job->password, [
                'id_segment' => 1
        ]);

        $this->assertInstanceOf(\Url\JobUrlStruct::class, $jobUrlStruct);
        $this->assertNotNull($jobUrlStruct->getTranslationUrl());
        $this->assertEquals($jobUrlStruct->getUrlByRevisionNumber(), $jobUrlStruct->getTranslationUrl());
        $this->assertNull($jobUrlStruct->getReviseUrl());
        $this->assertNull($jobUrlStruct->getRevise2Url());
        $this->assertNull($jobUrlStruct->getUrlByRevisionNumber(1));
        $this->assertNull($jobUrlStruct->getUrlByRevisionNumber(2));
        $this->assertNull($jobUrlStruct->getUrlByRevisionNumber("1"));
        $this->assertNull($jobUrlStruct->getUrlByRevisionNumber("2"));
        $this->assertFalse($jobUrlStruct->hasReview());
        $this->assertFalse($jobUrlStruct->hasSecondPassReview());
    }

    public function testBuildTranslationUrlFromBadCredentials(){

        $jobUrlStruct = \Url\JobUrlBuilder::createFromCredentials(-1, 'not_existing_password', [
                'id_segment' => 1543543543543
        ]);

        $this->assertNull($jobUrlStruct);
    }
}