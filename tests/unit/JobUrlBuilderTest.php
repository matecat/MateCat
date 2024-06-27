<?php


use TestHelpers\AbstractTest;
use Url\JobUrlBuilder;
use Url\JobUrlStruct;

class JobUrlBuilderTest extends AbstractTest {

    /**
     * @var Database
     */
    protected $database_instance;

    public function setUp() {
        parent::setUp();

        $this->database_instance = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );

        $this->database_instance->getConnection()->query(
                "INSERT INTO projects
                    ( password, id_customer, create_date, name )
                VALUES ( '3359e5740208', 'domenico@translated.net', '2019-06-19 15:36:08', 'MATECAT_PROJ-201906190336')"
        );
        $this->project = new Projects_ProjectStruct( $this->database_instance->getConnection()->query( "SELECT * FROM projects LIMIT 1" )->fetch() );

        $this->database_instance->getConnection()->query(
                "INSERT INTO jobs
                    ( password, id_project, job_first_segment, job_last_segment, tm_keys, disabled, create_date )
                    VALUES ( '92c5e0ce9316', " . $this->project[ 'id' ] . ", '4564373', '4564383', '[]', 0, '2019-06-21 15:22:14' )"
        );
        $this->job = new Jobs_JobStruct( $this->database_instance->getConnection()->query( "SELECT * FROM jobs LIMIT 1" )->fetch() );

    }

    public function testBuildTranslationUrl() {
        $job          = new Jobs_JobStruct( $this->database_instance->getConnection()->query( "SELECT * FROM jobs ORDER BY id DESC LIMIT 1" )->fetch() );
        $jobUrlStruct = JobUrlBuilder::createFromJobStruct( $job, [
                'id_segment' => 1
        ] );

        $this->assertInstanceOf( JobUrlStruct::class, $jobUrlStruct );
        $this->assertNotNull( $jobUrlStruct->getTranslationUrl() );
        $this->assertEquals( $jobUrlStruct->getUrlByRevisionNumber(), $jobUrlStruct->getTranslationUrl() );
        $this->assertNull( $jobUrlStruct->getReviseUrl() );
        $this->assertNull( $jobUrlStruct->getRevise2Url() );
        $this->assertNull( $jobUrlStruct->getUrlByRevisionNumber( 1 ) );
        $this->assertNull( $jobUrlStruct->getUrlByRevisionNumber( 2 ) );
        $this->assertNull( $jobUrlStruct->getUrlByRevisionNumber( "1" ) );
        $this->assertNull( $jobUrlStruct->getUrlByRevisionNumber( "2" ) );
        $this->assertFalse( $jobUrlStruct->hasReview() );
        $this->assertFalse( $jobUrlStruct->hasSecondPassReview() );
    }

    public function testBuildTranslationUrlFromCredentials() {
        $job          = new Jobs_JobStruct( $this->database_instance->getConnection()->query( "SELECT * FROM jobs ORDER BY id DESC LIMIT 1" )->fetch() );
        $jobUrlStruct = JobUrlBuilder::createFromJobStructAndProjectName( $job, 'fake_name', [
                'id_segment' => 1
        ] );

        $this->assertInstanceOf( JobUrlStruct::class, $jobUrlStruct );
        $this->assertNotNull( $jobUrlStruct->getTranslationUrl() );
        $this->assertContains( 'fake_name', $jobUrlStruct->getTranslationUrl() );
        $this->assertEquals( $jobUrlStruct->getUrlByRevisionNumber(), $jobUrlStruct->getTranslationUrl() );
        $this->assertNull( $jobUrlStruct->getReviseUrl() );
        $this->assertNull( $jobUrlStruct->getRevise2Url() );
        $this->assertNull( $jobUrlStruct->getUrlByRevisionNumber( 1 ) );
        $this->assertNull( $jobUrlStruct->getUrlByRevisionNumber( 2 ) );
        $this->assertNull( $jobUrlStruct->getUrlByRevisionNumber( "1" ) );
        $this->assertNull( $jobUrlStruct->getUrlByRevisionNumber( "2" ) );
        $this->assertFalse( $jobUrlStruct->hasReview() );
        $this->assertFalse( $jobUrlStruct->hasSecondPassReview() );
    }

}