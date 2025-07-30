<?php

class NewWithRevisionTypeTest extends IntegrationTest {

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

    function tests_project_type_is_saved_in_project() {
        $this->prepareUserAndKey();

        $this->headers = array(
            "X-MATECAT-KEY: {$this->test_data->api_key->api_key}",
            "X-MATECAT-SECRET: {$this->test_data->api_key->api_secret}"
        );

        $this->params = array(
            'project_name' => 'foo',
            'target_lang' => 'it-IT',
            'source_lang' => 'en-US',
            'metadata' => '{ "project_type" : "HT"}'
        );

        $this->files[] = test_file_path('xliff/amex-test.docx.xlf');

        $response = $this->getResponse() ;
        $body =  json_decode( $response['body'] );

        // check
        $project = Projects_ProjectDao::findById( $body->id_project );
        $dao = new Projects_MetadataDao(Database::obtain());

        $this->assertEquals('HT', $dao->get($project->id, 'project_type')->value );
    }


}
