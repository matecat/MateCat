<?php

class ProjectCompletionStatusTest extends IntegrationTest {

    private $test_data;

    function setup() {
        $this->test_data = new StdClass();
        parent::setup();
    }

    private function prepareUserAndApiKey() {
        $this->test_data->user    = Factory_User::create();
        $this->test_data->api_key = Factory_ApiKey::create( array(
                'uid' => $this->test_data->user->uid,
        ) );

        $this->test_data->headers = array(
                "X-MATECAT-KEY: {$this->test_data->api_key->api_key}",
                "X-MATECAT-SECRET: {$this->test_data->api_key->api_secret}"
        );
    }

    private function submitProjectWithApiKeys() {
        $this->test_data->project = integrationCreateTestProject(
                array( 'headers' => $this->test_data->headers )
        );
    }

    private function setValidProjectWithAllTranslatedSegments() {
        $this->prepareUserAndApiKey();

        Factory_OwnerFeature::create( array(
                'uid'          => $this->test_data->user->uid,
                'feature_code' => 'project_completion'
        ) );

        $this->submitProjectWithApiKeys();

        $this->test_data->chunks = integrationSetSegmentsTranslated(
                $this->test_data->project->id_project
        );
    }

    function testsCallOnValidProject() {
        $this->setValidProjectWithAllTranslatedSegments();
        $project = Projects_ProjectDao::findById( $this->test_data->project->id_project );

        foreach ( $this->test_data->chunks as $chunk ) {
            integrationSetChunkAsComplete( array(
                    'params' => array(
                            'id_job'   => $chunk->id,
                            'password' => $chunk->password
                    )
            ) );
        }

        $project       = Projects_ProjectDao::findById( $this->test_data->project->id_project );
        $expected_jobs = array();

        foreach ( $project->getJobs() as $job ) {
            $expected_jobs[] = array(
                    'id'           => $job->id,
                    'password'     => $job->password,
                    'download_url' => "http://" . $GLOBALS[ 'TEST_URL_BASE' ] . "/?action=downloadFile&id_job=$job->id&password=$job->password"
            );
        }

        $expected = array(
                'project_status' => array(
                        'id'        => $this->test_data->project->id_project,
                        'jobs'      => $expected_jobs,
                        'completed' => true,
                )
        );

        $test          = new CurlTest();
        $test->path    = '/api/v2/project-completion-status/' .
                $this->test_data->project->id_project;
        $test->method  = 'GET';
        $test->headers = $this->test_data->headers;
        $response      = $test->getResponse();

        $this->assertEquals( json_encode( $expected ), $response[ 'body' ] );

    }

    function testReturnsNonCompletedProject() {
        $this->setValidProjectWithAllTranslatedSegments();

        // get project chunks
        $chunksDao = new Chunks_ChunkDao( Database::obtain() );
        $chunks    = $chunksDao->getByProjectID(
                $this->test_data->project->id_project
        );
        $this->assertEquals( 1, count( $chunks ) );

        $chunk = $chunks[ 0 ];

        // split job in two
        $splitTest         = new CurlTest();
        $params            = array(
                'action'       => 'splitJob',
                'exec'         => 'apply',
                'project_id'   => $this->test_data->project->id_project,
                'project_pass' => $this->test_data->project->project_pass,
                'job_id'       => $chunk->id,
                'job_pass'     => $chunk->password,
                'num_split'    => 2,
                'split_values' => array( '5', '1' )
        );
        $splitTest->params = $params;
        $splitTest->method = 'POST';
        $response          = $splitTest->getResponse();

        $chunks = $chunksDao->getByProjectID(
                $this->test_data->project->id_project
        );
        $this->assertEquals( 2, count( $chunks ) );

        $first_chunk  = $chunks[ 0 ];
        $second_chunk = $chunks[ 1 ];

        integrationSetChunkAsComplete( array(
                'params' => array(
                        'id_job'   => $first_chunk->id,
                        'password' => $first_chunk->password
                )
        ) );

        $test          = new CurlTest();
        $test->path    = '/api/v2/project-completion-status/' .
                $this->test_data->project->id_project;
        $test->method  = 'GET';
        $test->headers = $this->test_data->headers;

        $response = $test->getResponse();
        $expected = array(
                'project_status' => array(
                        'id'        => $this->test_data->project->id_project,
                        'completed' => false,
                        'chunks'    => array(
                                array(
                                        'id'       => $second_chunk->id,
                                        'password' => $second_chunk->password
                                )
                        )
                )
        );

        $this->assertEquals( json_encode( $expected ), $response[ 'body' ] );

    }


}
