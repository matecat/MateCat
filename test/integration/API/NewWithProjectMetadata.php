<?php

class NewWithOwnershipTest extends IntegrationTest {

    private $test_data ;

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

    function tests_api_key_is_recognized() {
        $this->prepareUserAndKey();
        $this->params = array(
            'project_name' => 'foo',
            'target_lang' => 'it',
            'source_lang' => 'en',
            'revision_type' => 'HT'
        );

        $this->files[] = test_file_path('xliff/amex-test.docx.xlf');

        $response = $this->getResponse() ;
        $body =  json_decode( $response['body'] );

        // check
        //
        $project = Projects_ProjectDao::findById( $body->id_project );
        $metadata = $project->getMetadata();

        $this->assertEquals( 'revision_type', $metadata[0]->key);
        $this->assertEquals( 'HT', $metadata[0]->value);
    }

}
