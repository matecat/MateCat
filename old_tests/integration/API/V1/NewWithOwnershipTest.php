<?php

class NewWithOwnershipTest extends IntegrationTest {

    function setup() {
        $this->path = '/api/new' ;
        $this->method = 'POST';

        $this->test_data = new StdClass();

        parent::setup();
    }

    private function prepareUserAndKey() {
        $this->test_data->user  = Factory_User::create() ;
        $this->test_data->api_key = Factory_ApiKey::create(array(
            'uid' => $this->test_data->user->uid,
        ));
    }

    function test_api_key_is_recognized() {
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

        $this->files[] = test_file_path('xliff/amex-test.docx.xlf');

        $response = $this->getResponse() ;
        $body =  json_decode( $response['body'] );

        // check
        //
        $project = Projects_ProjectDao::findById( $body->id_project );

        $this->assertEquals( $project->id_customer, $this->test_data->user->email );
        $this->assertEquals( $response['code'], 200 );
    }

    function test_wrong_key_returns_401() {
        $this->prepareUserAndKey();

        $this->headers = array(
            "X-MATECAT-KEY: not-a-valid-key",
            "X-MATECAT-SECRET: {$this->test_data->api_key->api_secret}"
        );

        $this->params = array(
            'project_name' => 'foo',
            'target_lang' => 'it-IT',
            'source_lang' => 'en-US',
        );

        $this->files[] = test_file_path('xliff/amex-test.docx.xlf');

        $response = $this->getResponse() ;
        $body     = json_decode( $response['body'] );

        $this->assertEquals( $response['code'], 401);
        $this->assertEquals( $body->message, 'Authentication failed' );
    }

    function test_missing_auth_sets_project_to_translated_user() {
        $this->params = array(
            'project_name' => 'foo',
            'target_lang' => 'it-IT',
            'source_lang' => 'en-US',
        );

        $this->files[] = test_file_path('xliff/amex-test.docx.xlf');

        $response = $this->getResponse() ;
        $body     = json_decode( $response['body'] );

        $project = Projects_ProjectDao::findById( $body->id_project );
        $this->assertEquals( $project->id_customer, ProjectManager::TRANSLATED_USER );

    }



}
