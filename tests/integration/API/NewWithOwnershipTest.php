<?php

class NewWithOwnershipTest extends IntegrationTest {

    function setup() {
        $this->path = '/api/new' ;
        $this->method = 'POST';

        parent::setup();
    }

    function testsApiKeyIsRecognized() {
        // prepare
        //
        $user = Factory_User::create(array(
            'email' => 'foo@example.org'
        ));

        $this->assertNotNull( $user->uid ) ;

        $apiKey = Factory_ApiKey::create(array(
            'uid' => $user->uid
        ));

        // run
        //
        $this->headers = array(
            "AUTH_MATECAT_KEY: {$apiKey->api_key}",
            "AUTH_MATECAT_SECRET: {$apiKey->api_secret}"
        );

        $this->params = array(
            'project_name' => 'foo',
            'target_lang' => 'it',
            'source_lang' => 'en',
        );

        $this->files[] = test_file_path('amex-test.docx.xlf');

        $response =  json_decode( $this->makeRequest() );

        // check
        //
        $project = Projects_ProjectDao::findById( $response->id_project );

        $this->assertEquals( $project->id_customer, 'foo@example.org' );

    }

}
