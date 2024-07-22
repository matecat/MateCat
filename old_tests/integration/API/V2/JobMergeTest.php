<?php

use LQA\ChunkReviewDao;

class JobMergeTest extends IntegrationTest {

    private $test_data;

    function setup() {
        $this->test_data = new StdClass();

        $this->prepareUserAndApiKey();
    }

    function test_merge_api() {
        $project = integrationCreateTestProject( array(
                'headers' => $this->test_data->headers,
                'files' => array(
                        test_file_path('zip-with-model-json.zip')
                )
        ));

        $project = Projects_ProjectDao::findById( $project->id_project );

        $chunks = $project->getChunks();
        splitJob(array(
                'id_job'       => $chunks[0]->id,
                'id_project'   => $project->id,

                'project_pass' => $project->password,
                'job_pass'     => $chunks[0]->password,
                'num_split'    => 2,
                'split_values' => array(10, 11)
        ));

        $chunks = $project->getChunks();
        $this->assertEquals(2, count( $chunks ));

        // make request to the APIs
        $test          = new CurlTest();
        $test->path    = sprintf('/api/v2/projects/%s/%s/jobs/%s/merge',
                $project->id,
                $project->password,
                $chunks[0]->id
        );

        $test->method  = 'POST';
        $test->headers = $this->test_data->headers;
        $response = $test->getResponse();

        $chunks = $project->getChunks();
        $this->assertEquals(1, count($chunks));

    }

    function test_chunk_options_cleanup_after_merge() {
        $project = integrationCreateTestProject( array(
            'headers' => $this->test_data->headers,
            'files' => array(
                test_file_path('zip-with-model-json.zip')
            )
        ));

        $project = Projects_ProjectDao::findById( $project->id_project );

        $chunks = $project->getChunks();

        splitJob(array(
            'id_job'       => $chunks[0]->id,
            'id_project'   => $project->id,
            'project_pass' => $project->password,
            'job_pass'     => $chunks[0]->password,
            'num_split'    => 2,
            'split_values' => array(10, 11)
        ));

        $chunks = $project->getChunks();

        $speech2text_key = 'speech2text';

        foreach ($chunks as $chunk) {
            $metadata_dao = new \Projects_MetadataDao();

            toggleChunkOptions(array(
                'id_job'       => $chunk->id,
                'job_pass'     => $chunk->password,
                'features'     => array( $speech2text_key => true )
            ));

            $chunk_option = $metadata_dao->get(
                $project->id,
                \Projects_MetadataDao::buildChunkKey( $speech2text_key, $chunk )
            );

            $this->assertNotNull( $chunk_option, 'chunk option created' );
        }

        mergeJob(array(
            'id_job'       => $chunks[0]->id,
            'id_project'   => $project->id,
            'project_pass' => $project->password,
        ));

        $chunks = $project->getChunks();

        foreach ($chunks as $chunk) {
            $metadata_dao = new \Projects_MetadataDao();

            $chunk_option = $metadata_dao->get(
                $project->id,
                \Projects_MetadataDao::buildChunkKey( $speech2text_key, $chunk )
            );

            $this->assertNull( $chunk_option, 'chunk option removed' );
        }
    }
}