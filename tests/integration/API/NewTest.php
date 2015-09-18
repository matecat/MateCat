<?php

class NewTest extends IntegrationTest {
    function setup() {
        $this->path = '/api/new' ;
        $this->method = 'POST';

        parent::setup();
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
            'source_lang' => 'en',
            'private_tm_key' => 'f05960431f879c750f48'
        );

        // $this->files[] = test_file_path('amex-test.docx.xlf');
        $this->files[] = test_file_path('amex-test.docx.xlf');

        $response =  json_decode( $this->makeRequest() );

        Log::doLog( $response );

        $this->assertEquals( 'Success', $response->message);
        $this->assertEquals( 'OK',      $response->status );
        $this->assertNotNull( $response->id_project );
        $this->assertNotNull( $response->project_pass );
    }

    function testIgnoresReferenceFiles() {
        $this->params = array(
            'project_name' => 'foo',
            'target_lang' => 'it',
            'source_lang' => 'en'
        );

        $this->files[] = test_file_path('zip-with-reference-files.zip');

        $response =  json_decode( $this->makeRequest() );

        $this->assertEquals( 'Success', $response->message);
        $this->assertEquals( 'OK',      $response->status );
        $this->assertNotNull( $response->id_project );
        $this->assertNotNull( $response->project_pass );

        $filesDao = new Files_FileDao( Database::obtain() );

        $files = $filesDao->getByProjectId( $response->id_project );

        $this->assertEquals( 1, count($files) ) ;
        $this->assertEquals( 'zip-with-reference-files.zip___SEP___amex-test.docx.xlf', $files[0]['filename'] );
    }

}
