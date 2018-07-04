<?php

class NewWithQAModelTest extends IntegrationTest {

    function setup() {
        $this->path = '/api/new' ;
        $this->method = 'POST';

        $this->test_data = new StdClass();

        parent::setup();
    }

    private function prepareUserAndKey() {
        $this->test_data->user  = Factory_User::create() ;

        $feature = Factory_OwnerFeature::create( array(
            'uid'          => $this->test_data->user->uid,
            'feature_code' => Features::REVIEW_IMPROVED
        ) );

        $this->test_data->api_key = Factory_ApiKey::create(array(
            'uid' => $this->test_data->user->uid,
        ));
    }

    /**
     * In this test the qa_model.json file is contained in the zip
     * file and we are testing that the project creation evaluates
     * the source file correctly and that the record is created in
     * the database.
     */

    function tests_qa_model_is_created_from_json_file() {
        $this->prepareUserAndKey();

        $this->headers = array(
            "X-MATECAT-KEY: {$this->test_data->api_key->api_key}",
            "X-MATECAT-SECRET: {$this->test_data->api_key->api_secret}"
        );

        $this->params = array(
            'project_name' => 'foo',
            'target_lang' => 'it-IT',
            'source_lang' => 'en-US',
        );

        $this->files[] = test_file_path( 'zip-with-model-json.zip' );

        $response = $this->getResponse() ;
        $body = json_decode( $response['body'] );

        $project = Projects_ProjectDao::findById( $body->id_project );

        $this->assertEquals( $project->id_customer, $this->test_data->user->email );
        $this->assertEquals( $response['code'], 200 );

        $model = $project->getLqaModel();
        $this->assertInstanceOf('LQA\ModelStruct', $model );

        $categories = json_decode ( $model->getSerializedCategories(), true );
        $this->assertEquals( 'Accuracy', $categories['categories'][0]['label']);
    }
}
