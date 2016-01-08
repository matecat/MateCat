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

        $project = integrationCreateTestProject( array(
            'headers' => $this->test_data->headers,
            'files' => array(
                test_file_path('zip-with-model-json.zip')
            )
        ));

        $this->params = array(
            'id_project' => $project->id_project,
            'project_pass' => $project->project_pass
        );

        $project = Projects_ProjectDao::findById( $project->id_project );

        $this->assertNotNull( $project->id );
        $reviews = JobReviewDao::findByProjectId( $project->id ) ;
        $this->assertNotEmpty( $reviews );

        var_dump( $reviews );
    }

}
