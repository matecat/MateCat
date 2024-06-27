<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 12/07/16
 * Time: 10:30
 */
class ValidateSourceAndTargetLanguagesTest extends IntegrationTest {

    private $test_data ;

    function setup() {
        $this->path = '/api/new' ;
        $this->method = 'POST';

        $this->test_data = new StdClass();

        parent::setup();
    }

    function test_RFC_format_is_accepted() {
        $this->params = array(
                'project_name' => 'foo',
                'target_lang' => 'it-IT,es-ES',
                'source_lang' => 'en-US',
        );

        $this->files[] = test_file_path('xliff/amex-test.docx.xlf');

        $response = $this->getResponse() ;
        $body =  json_decode( $response['body'] );

        $project = Projects_ProjectDao::findById( $body->id_project );

        $jobs = $project->getChunks();

        $this->assertEquals('en-US', $jobs[0]->source );
        $this->assertEquals('it-IT', $jobs[0]->target );

        $this->assertEquals('en-US', $jobs[1]->source ) ;
        $this->assertEquals('es-ES', $jobs[1]->target ) ;

    }

    function test_not_RFC_format_is_rejected() {
        $this->params = array(
                'project_name' => 'foo',
                'target_lang' => 'it,es-ES',
                'source_lang' => 'en-US',
        );

        $this->files[] = test_file_path('xliff/amex-test.docx.xlf');

        $response = $this->getResponse() ;
        $body =  json_decode( $response['body'] );

        $this->assertEquals( 'FAIL', $body->status );
        $this->assertStringMatchesFormat("Invalid language code: it", $body->message ) ;

    }

}