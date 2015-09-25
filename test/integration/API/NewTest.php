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

        $response = $this->getResponse() ;
        $this->assertJSONResponse( $expected, $response['body'] );
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

        $response = $this->getResponse() ;
        $this->assertJSONResponse( $expected, $response['body'] );
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

        $response = $this->getResponse() ;
        $this->assertJSONResponse( $expected, $response['body'] );
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

        $response = $this->getResponse() ;
        $response = json_decode( $response['body'] );

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

        $response =  $this->makeRequest();
        $response =  json_decode( $response['body'] );

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
