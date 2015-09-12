<?php

class NewControllerTest extends IntegrationTest {

    function setup() {
        $this->path = '/api/new' ;
        $this->method = 'POST';
    }

    function testValidatesTargetLanguage() {
        $this->params = array('project_name' => 'foo');

        $expected = array(
            'status' => 'FAIL',
            'message' => "Missing target language."
        );

        $response = $this->makeRequest();
        $this->assertJSONResponse( $expected, $response );
    }

    function testValidatesSourceLanguage() {
        $this->params = array(
            'project_name' => 'foo',
            'target_lang' => 'it'
        );

        $expected = array(
            'status' => 'FAIL',
            'message' => "Missing source language."
        );

        $response = $this->makeRequest();
        $this->assertJSONResponse( $expected, $response );
    }

    function testValidatesMissingFiles() {
        $this->params = array(
            'project_name' => 'foo',
            'target_lang' => 'it',
            'source_lang' => 'en'
        );

        $expected = array(
            'status' => 'FAIL',
            'message' => "No files received."
        );

        $response = $this->makeRequest();
        $this->assertJSONResponse( $expected, $response );
    }

    function testSubmitsProject() {
        $this->params = array(
            'project_name' => 'foo',
            'target_lang' => 'it',
            'source_lang' => 'en'
        );

        $this->files[] = test_file_path('amex-test.docx.xlf');

        $response =  json_decode( $this->makeRequest() );

        $this->assertEquals( 'Success', $response->message);
        $this->assertEquals( 'OK',      $response->status );
        $this->assertNotNull( $response->id_project );
        $this->assertNotNull( $response->project_pass );

    }
}
