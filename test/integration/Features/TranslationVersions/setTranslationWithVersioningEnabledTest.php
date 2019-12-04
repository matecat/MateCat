<?php

use Features\TranslationVersions\Model\TranslationVersionDao;

class setTranslationWithVerioningEnabledTest extends IntegrationTest {
    private $test_data=array();

    function setUp() {
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

    function tests_translations_are_versioned() {

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

        $this->assertEquals( 1,  count( $versions ) );
        $version = $versions[0];
        $this->assertEquals('This is the original translation', $version->translation);

    }

    function tests_no_changes_to_the_text_causes_no_new_version() {
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

        integrationSetTranslation( array(
          'id_segment' => $segment->id ,
          'id_job' => $chunk->id,
          'password' => $chunk->password,
          'status' => 'translated',
          'translation' => 'New Translation!' # <-- this is the same as before
        ) ) ;

        $translations = $chunk->getTranslations();

        $versions = TranslationVersionDao::getVersionsForTranslation(
          $translations[0]->id_job, $translations[0]->id_segment
        );

        $this->assertEquals( 1,  count( $versions ) );
    }

    function tests_propagated_segments_are_versioned() {
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

        $this->assertEquals( 7,  count( $versions ) );

        $i=0; while($i < 7) {
          $version = $versions[0];
          $this->assertEquals('Palavra En Inglês', $version->translation);
          $i++;
        }

    }

    function tests_same_translation_submitted_does_not_increase_versions() {
        $project = integrationCreateTestProject(array(
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

        integrationSetTranslation( array(
          'id_segment'  => $segment->id ,
          'id_job'      => $chunk->id,
          'password'    => $chunk->password,
          'status'      => 'translated',
          'translation' => 'This is translated!',
          # 'propagate'   => true
          # ^^^^^^^^^^^^^^^^^^^^^
          # Previously propagated segments propagate even
          # if `propagate` is not submitted.
        ) ) ;

        $translations = $chunk->getTranslations();

        $versions = TranslationVersionDao::getVersionsForJob(
          $translations[0]->id_job
        );

        $this->assertEquals( 7,  count( $versions ) );

        $i=0; while($i < 7) {
          $version = $versions[0];
          $this->assertEquals('Palavra En Inglês', $version->translation);
          $i++;
        }

    }

}
