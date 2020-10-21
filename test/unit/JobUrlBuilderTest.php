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
        $job = new Jobs_JobStruct( $this->database_instance->getConnection()->query( "SELECT * FROM jobs LIMIT 1" )->fetch() );
        $url = \Url\JobUrlBuilder::create($job->id, $job->password, [
            'id_segment' => 1000
        ]);

        $this->assertNotNull($url);
        $this->assertContains('#', $url);
    }
}