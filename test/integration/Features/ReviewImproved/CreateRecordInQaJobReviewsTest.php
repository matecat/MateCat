<?php

use \LQA\JobReviewDao ;

class CreateRecordInQaJobReviewsTest extends IntegrationTest {

    function setUp() {
        $this->test_data->user = Factory_User::create();

        $feature = Factory_OwnerFeature::create( array(
            'uid'          => $this->test_data->user->uid,
            'feature_code' => Features::REVIEW_IMPROVED
        ) );

        $this->test_data->api_key = Factory_ApiKey::create( array(
            'uid' => $this->test_data->user->uid,
        ) );

        $this->test_data->headers = array(
            "X-MATECAT-KEY: {$this->test_data->api_key->api_key}",
            "X-MATECAT-SECRET: {$this->test_data->api_key->api_secret}"
        );

    }

    function tests_qa_job_reivew_record_is_present() {
        $project = $this->createProject();
        $project = Projects_ProjectDao::findById( $project->id_project );

        $this->assertNotNull( $project->id );
        $reviews = JobReviewDao::findByProjectId( $project->id ) ;
        $this->assertNotEmpty( $reviews );
    }

    function tests_edit_records_when_job_is_splitted() {
        $project = $this->createProject();
        $project = Projects_ProjectDao::findById( $project->id_project );

        $chunks = $project->getChunks();

        splitJob(array(
            'id_job'       => $chunks[0]->id,
            'id_project'   => $project->id,
            'exec'         => 'apply',
            'project_pass' => $project->password,
            'job_pass'     => $chunks[0]->password,
            'num_split'    => 2,
            'split_values' => array(10, 11)
        ));

        $chunks = $project->getChunks();
        $this->assertEquals(2, count( $chunks ) );
    }

    private function createProject() {
        return integrationCreateTestProject( array(
            'headers' => $this->test_data->headers,
            'files' => array(
                test_file_path('zip-with-model-json.zip')
            )
        ));
    }
}
