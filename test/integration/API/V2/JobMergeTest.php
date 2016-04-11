<?php

use LQA\ChunkReviewDao;

class JobMergeTest extends IntegrationTest {

    private $test_data;

    function setup() {
        $this->test_data = new StdClass();

        $this->prepareUserAndApiKey();
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

}