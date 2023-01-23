<?php

use Features\TranslationVersions\Model\TranslationVersionDao;

require 'lib/Controller/API/V2/SegmentVersion.php';
require 'lib/Controller/API/V2/Validators/JobPasswordValidator.php';



class SegmentVersionTest extends IntegrationTest {

    private $test_data = array();

    function setup() {
        
        $this->test_data = new stdClass();
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

    function tests_should_get_info_on_versions() {

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
        $translation = $translations[0];

        $test          = new CurlTest();
        $test->path    = sprintf("/api/v2/jobs/%s/%s/segments/%s/translation-versions",
            $chunk->id, $chunk->password, $segment->id
        );
        $test->method  = 'GET';
        $test->headers = $this->test_data->headers;

        $response = $test->getResponse();

        $versions = TranslationVersionDao::getVersionsForTranslation( $chunk->id, $segment->id );
        $version = $versions[0];

        $expected = array(
            'versions' => array(
                array(
                    'id'              => $version->id,
                    'id_segment'       => $version->id_segment,
                    'id_job'           => $version->id_job,
                    'translation'     => $version->translation,
                    'version_number'  => $version->version_number,
                    'propagated_from' => $version->propagated_from,
                    'created_at'       => $version->creation_date,
                )
            )
        );

        $this->assertEquals( json_encode( $expected ), $response[ 'body' ] );
    }

}
