<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 22/07/16
 * Time: 12:04
 */
class setPrivateTMKeyTest extends IntegrationTest {

    function setUp() {
        $this->test_data = new stdClass();
    }

    function test_set_private_tm_key_works() {
        $upload_session = uniqid();

        $file = test_file_path('xliff/amex-test.docx.xlf') ;
        prepare_file_in_upload_folder( $file, $upload_session );

        do_file_conversion(array(
                'source_lang' => 'en-US',
                'target_lang' => 'it-IT',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session
        ));

        $response = createProjectWithUIParams( array(
                'source_language' => 'en-US',
                'target_language' => 'it-IT',
                'pretranslate_100' => 1,
                'job_subject' => 'general',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session,
                'files' => array( $file ),
                'private_tm_key' => '23154e7f6f93a838f7bc'
        ));

        $json_response = json_decode( $response['body'], TRUE );

        $id_project = $json_response['id_project'];

        $project = Projects_ProjectDao::findById( $id_project );
        $this->assertNotNull( $project->id );

        $chunks = $project->getChunks();
        $this->assertEquals(1, count( $chunks ));
        $keys = TmKeyManagement_TmKeyManagement::getJobTmKeys( $chunks[0]->tm_keys);

        $this->assertEquals(1, count( $keys ));
        $this->assertEquals( '23154e7f6f93a838f7bc', $keys[0]->key );
    }

    function test_set_private_tm_key_from_list_works() {
        $upload_session = uniqid();

        $file = test_file_path('xliff/amex-test.docx.xlf') ;
        prepare_file_in_upload_folder( $file, $upload_session );

        do_file_conversion(array(
                'source_lang' => 'en-US',
                'target_lang' => 'it-IT',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session
        ));

        $response = createProjectWithUIParams( array(
                'source_language' => 'en-US',
                'target_language' => 'it-IT',
                'pretranslate_100' => 1,
                'job_subject' => 'general',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session,
                'files' => array( $file ),
                'private_keys_list' => '{"ownergroup":[],"mine":[{"tm":"1","glos":"1","key":"23154e7f6f93a838f7bc","name":"en-es-test.g","r":1,"w":1}],"anonymous":[]}'
        ));

        $json_response = json_decode( $response['body'], TRUE );

        $id_project = $json_response['id_project'];

        $project = Projects_ProjectDao::findById( $id_project );
        $this->assertNotNull( $project->id );

        $chunks = $project->getChunks();
        $this->assertEquals(1, count( $chunks ));
        $keys = TmKeyManagement_TmKeyManagement::getJobTmKeys( $chunks[0]->tm_keys);

        $this->assertEquals(1, count( $keys ));
        $this->assertEquals( '23154e7f6f93a838f7bc', $keys[0]->key );
    }


}