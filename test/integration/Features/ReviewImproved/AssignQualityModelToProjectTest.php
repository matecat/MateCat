<?php

class AssignQualityModelToProjectTest extends IntegrationTest {

    private $test_data=array();

    function setUp() {
        $this->test_data=new stdClass();
        $this->test_data->user = Factory_User::create();

        $feature = Factory_OwnerFeature::create( array(
            'uid'          => $this->test_data->user->uid,
            'feature_code' => Features::REVIEW_IMPROVED,
            'options'      => json_encode( array( 'id_qa_model' => 1 ) )
        ) );

        $this->test_data->api_key = Factory_ApiKey::create( array(
            'uid' => $this->test_data->user->uid,
        ) );

        $this->test_data->headers = array(
            "X-MATECAT-KEY: {$this->test_data->api_key->api_key}",
            "X-MATECAT-SECRET: {$this->test_data->api_key->api_secret}"
        );
    }

    function tests_qa_model_is_assigned_to_project() {

        $project = integrationCreateTestProject( array(
            'headers' => $this->test_data->headers
        ));

        $this->params = array(
            'id_project' => $project->id_project,
            'project_pass' => $project->project_pass
        );

        $project = Projects_ProjectDao::findById( $project->id_project );

        $this->assertNotNull( $project->id );
        $this->assertEquals( '1', $project->id_qa_model );
    }

}
