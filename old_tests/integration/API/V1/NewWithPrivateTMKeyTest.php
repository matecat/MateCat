<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 22/07/16
 * Time: 10:33
 */
class NewWithPrivateTMKeyTest extends IntegrationTest {

    function setup() {
        $this->path = '/api/new' ;
        $this->method = 'POST';

        $this->test_data = new StdClass();

        parent::setup();
    }


    function test_api_key_is_recognized() {
        $this->params = array(
                'project_name' => 'foo',
                'target_lang' => 'it-IT',
                'source_lang' => 'en-US',
                'private_tm_key' => '23154e7f6f93a838f7bc',
        );

        $this->files[] = test_file_path('xliff/amex-test.docx.xlf');

        $response = $this->getResponse() ;
        $body =  json_decode( $response['body'] );

        $project = Projects_ProjectDao::findById( $body->id_project );
        $chunks = $project->getChunks();
        $this->assertEquals(1, count( $chunks ));
        $keys = TmKeyManagement_TmKeyManagement::getJobTmKeys( $chunks[0]->tm_keys);

        $this->assertEquals(1, count( $keys ));

        $this->assertEquals('23154e7f6f93a838f7bc', $keys[0]->key );

    }

}