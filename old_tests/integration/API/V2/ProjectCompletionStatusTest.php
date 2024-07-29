<?php

class ProjectCompletionStatusTest extends IntegrationTest {

    private $test_data;

    function setup() {
        $this->test_data = new stdClass();
        // parent::setup();
    }

    private function submitProjectWithApiKeys() {
        $this->test_data->project = integrationCreateTestProject(
                array( 'headers' => $this->test_data->headers )
        );
    }

    private function setValidProjectWithAllTranslatedSegments() {
        $this->createProject();
        $this->setAllSegmentsTranslated();
    }

    private function setAllSegmentsTranslated() {
        $this->test_data->chunks = integrationSetSegmentsTranslated(
            $this->test_data->project->id_project
        );
    }

    private function createProject() {
        $this->prepareUserAndApiKey();

        Factory_OwnerFeature::create( array(
                'uid'          => $this->test_data->user->uid,
                'feature_code' => 'project_completion'
        ) );

        $this->submitProjectWithApiKeys();
    }

    function test_is_not_complete_when_segments_are_translated() {
        $this->setValidProjectWithAllTranslatedSegments();

        $project = Projects_ProjectDao::findById( $this->test_data->project->id_project );

        $expected_jobs = array();
        $jobs = $project->getJobs();

        $expected = array(
                'project_status' => array(
                        'revise'   => array(
                            array(
                                'id' => $jobs[0]->id,
                                'password' => $jobs[0]->password,
                                'completed' => false,
                                'completed_at' => null
                            )
                        ),
                        'translate' => array(
                            array(
                                'id' => $jobs[0]->id,
                                'password' => $jobs[0]->password,
                                'completed' => false,
                                'completed_at' => null
                            )
                        ),
                        'id'        => $this->test_data->project->id_project,
                        'completed' => false,
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

    function test_is_not_complete_by_default() {
        $this->createProject();
        $project = Projects_ProjectDao::findById( $this->test_data->project->id_project );

        $expected_jobs = array();
        $jobs = $project->getJobs();

        $expected = array(
                'project_status' => array(
                        'revise'   => array(
                            array(
                                'id' => $jobs[0]->id,
                                'password' => $jobs[0]->password,
                                'completed' => false,
                                'completed_at' => null
                            )
                        ),
                        'translate' => array(
                            array(
                                'id' => $jobs[0]->id,
                                'password' => $jobs[0]->password,
                                'completed' => false,
                                'completed_at' => null
                            )
                        ),
                        'id'        => $this->test_data->project->id_project,
                        'completed' => false,
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

    function test_is_complete_when_review_and_translate_are_complete() {
        $this->setValidProjectWithAllTranslatedSegments();

        $project = Projects_ProjectDao::findById( $this->test_data->project->id_project );

        sleep(1);
        foreach ( $this->test_data->chunks as $chunk ) {
            integrationSetChunkAsComplete( array(
                    'referer' => 'http://example.org/translate/foo/bar',
                    'params' => array(
                            'id_job'   => $chunk->id,
                            'password' => $chunk->password
                    )
            ) );
            integrationSetChunkAsComplete( array(
                    'referer' => 'http://example.org/revise/foo/bar',
                    'params' => array(
                            'id_job'   => $chunk->id,
                            'password' => $chunk->password
                    )
            ) );
        }

        $project       = Projects_ProjectDao::findById( $this->test_data->project->id_project );
        $expected_jobs = array('translate' => array(), 'revise' => array() );


        foreach ( $project->getChunks() as $job ) {
            $translate = Chunks_ChunkCompletionEventDao::lastCompletionRecord(
                    $job, array('is_review' => false));
            $revise = Chunks_ChunkCompletionEventDao::lastCompletionRecord(
                    $job, array('is_review' => true));

            $expected_jobs['translate'][] = array(
                    'id'           => $job->id,
                    'password'     => $job->password,
                    'completed'    => true,
                    'completed_at' => Utils::api_timestamp( $translate['create_date'] )
            );

            $expected_jobs['revise'][] = array(
                    'id'           => $job->id,
                    'password'     => $job->password,
                    'completed'    => true,
                    'completed_at' => Utils::api_timestamp( $revise['create_date'] )
            );
        }

        $expected = array(
                'project_status' => array(
                        'revise'      => $expected_jobs['revise'],
                        'translate'  => $expected_jobs['translate'],
                        'id'        => $this->test_data->project->id_project,
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

    function test_returns_uncomplete_splitted_job_correctly() {
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

        sleep(1);

        // set chunk completed for translate page
        integrationSetChunkAsComplete( array(
            'referer' => 'http://example.org/translate/rest-of-path',
            'params' => array(
                'id_job'   => $first_chunk->id,
                'password' => $first_chunk->password
            )
        ) );

        // set chunk completed for revise page
        integrationSetChunkAsComplete( array(
            'referer' => 'http://example.org/revise/rest-of-path',
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

        $first_translate = Chunks_ChunkCompletionEventDao::lastCompletionRecord(
                $first_chunk, array('is_review' => false));
        $first_revise  = Chunks_ChunkCompletionEventDao::lastCompletionRecord(
                $first_chunk, array('is_review' => true));

        $expected = array(
                'project_status' => array(
                        'revise'    => array(
                                array(
                                        'id'       => $first_chunk->id,
                                        'password' => $first_chunk->password,
                                        'completed' => true,
                                        'completed_at' => Utils::api_timestamp( $first_revise['create_date'])
                                ),
                                array(
                                        'id'       => $second_chunk->id,
                                        'password' => $second_chunk->password,
                                        'completed' => false,
                                        'completed_at' => null
                                )
                        ),
                        'translate'    => array(
                                array(
                                        'id'       => $first_chunk->id,
                                        'password' => $first_chunk->password,
                                        'completed' => true,
                                        'completed_at' => Utils::api_timestamp( $first_translate['create_date'])
                                ),
                                array(
                                        'id'       => $second_chunk->id,
                                        'password' => $second_chunk->password,
                                        'completed' => false,
                                        'completed_at' => null
                                )
                        ),
                        'id'        => $this->test_data->project->id_project,
                        'completed' => false,
                )
        );

        $this->assertEquals( json_encode( $expected ), $response[ 'body' ] );

    }


}
