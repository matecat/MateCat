<?php

class increaseVersionNumberTest extends IntegrationTest {
    private $test_data=array();

    function setUp(){
        $this->test_data=new stdClass();
        $this->test_data->user = Factory_User::create();

        $feature = Factory_OwnerFeature::create( array(
            'uid'          => $this->test_data->user->uid,
            'feature_code' => Features::TRANSLATION_VERSIONS
        ) );

        $this->test_data->api_key = Factory_ApiKey::create( array(
            'uid' => $this->test_data->user->uid,
        ) );

        $this->test_data->headers = array(
            "X-MATECAT-KEY: {$this->test_data->api_key->api_key}",
            "X-MATECAT-SECRET: {$this->test_data->api_key->api_secret}"
        );
    }

    function tests_increases_version_number_in_segment_translations() {
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
                'id_segment'  => $segment->id,
                'id_job'      => $chunk->id,
                'password'    => $chunk->password,
                'status'      => 'translated',
                'translation' => 'New Translation!' # <-- this changed
        ) );

        $translations = $chunk->getTranslations();

        $this->assertEquals(1, $translations[0]->version_number );

        integrationSetTranslation( array(
                'id_segment'  => $segment->id,
                'id_job'      => $chunk->id,
                'password'    => $chunk->password,
                'status'      => 'translated',
                'translation' => 'translation changed again!' # <-- this changed
        ) );

        $translations = $chunk->getTranslations();

        $this->assertEquals(2, $translations[0]->version_number );

    }

    function tests_increases_version_number_in_translation_propagation() {
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

        foreach( $translations as $key => $translation) {
            $this->assertEquals(1, $translation->version_number, "On record number $key " );
        }

    }
}
