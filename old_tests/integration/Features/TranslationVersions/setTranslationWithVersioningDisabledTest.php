<?php

use Features\TranslationVersions\Model\TranslationVersionDao;

class setTranslationWithVerioningDisabledTest extends IntegrationTest {
    private $test_data=array();

    function setUp() {
        $this->test_data=new stdClass();
        $this->test_data->user = Factory_User::create();

        // Feature TRANSLATION_VERSIONS is not set for this test
        //
        // $feature = Factory_OwnerFeature::create( array(
        //     'uid'          => $this->test_data->user->uid,
        //     'feature_code' => Features::TRANSLATION_VERSIONS
        // ) );

        $this->test_data->api_key = Factory_ApiKey::create( array(
            'uid' => $this->test_data->user->uid,
        ) );

        $this->test_data->headers = array(
            "X-MATECAT-KEY: {$this->test_data->api_key->api_key}",
            "X-MATECAT-SECRET: {$this->test_data->api_key->api_secret}"
        );
    }

    function tests_no_versioning_on_translation() {

        $project = integrationCreateTestProject( array(
            'headers' => $this->test_data->headers
        ));

        $this->params = array(
            'id_project' => $project->id_project,
            'project_pass' => $project->project_pass
        );

        $chunksDao = new Chunks_ChunkDao( Database::obtain() ) ;
        $chunks = $chunksDao->getByProjectID( $project->id_project );
        $chunk = $chunks[0];

        $this->assertTrue( count($chunks) == 1);

        $segments = $chunk->getSegments();
        $segment = $segments[0];

        integrationSetTranslation( array(
          'id_segment' => $segment->id ,
          'id_job' => $chunk->id,
          'password' => $chunk->password,
          'status' => 'translated',
          'translation' => 'This is the original translation'
        ) ) ;

        integrationSetTranslation( array(
          'id_segment' => $segment->id ,
          'id_job' => $chunk->id,
          'password' => $chunk->password,
          'status' => 'translated',
          'translation' => 'New Translation!' # <-- this changed
        ) ) ;

        $translations = $chunk->getTranslations();

        $versions = TranslationVersionDao::getVersionsForTranslation(
          $translations[0]->id_job, $translations[0]->id_segment
        );

        $this->assertEquals( 0,  count( $versions ) );

    }

    function tests_no_versioning_on_propagation() {
        $project = integrationCreateTestProject( array(
            'headers' => $this->test_data->headers,
            'files' => array( test_file_path('test-propagation.xlf'))
        ));

        $this->params = array(
            'id_project'   => $project->id_project,
            'project_pass' => $project->project_pass
        );

        $chunksDao = new Chunks_ChunkDao( Database::obtain() ) ;
        $chunks = $chunksDao->getByProjectID( $project->id_project );
        $chunk = $chunks[0];

        $this->assertTrue( count($chunks) == 1);

        $segments = $chunk->getSegments();
        $segment = $segments[0];

        integrationSetTranslation( array(
          'id_segment'  => $segment->id ,
          'id_job'      => $chunk->id,
          'password'    => $chunk->password,
          'status'      => 'translated',
          'translation' => 'This is translated!',
          'propagate'   => true
        ) ) ;

        $translations = $chunk->getTranslations();

        $versions = TranslationVersionDao::getVersionsForJob(
          $translations[0]->id_job
        );

        $this->assertEquals( 0,  count( $versions ) );

    }


}
