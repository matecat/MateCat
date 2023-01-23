<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 12/07/16
 * Time: 14:50
 */
class sourceAndTargetLangValidationTest extends IntegrationTest {

    private $test_data=array();

    function setUp() {
        $this->test_data=new stdClass();
    }

    function test_source_lang_must_be_RFC() {
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
                'source_language' => 'en',
                'target_language' => 'it',
                'pretranslate_100' => 1,
                'job_subject' => 'general',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session,
                'files' => array( $file )
        ));

        $json_response = json_decode( $response['body'], TRUE );

        $this->assertStringMatchesFormat('Invalid language code: en',  $json_response['errors'][0]['message']);
        $this->assertStringMatchesFormat('Invalid language code: it',  $json_response['errors'][1]['message']);
    }


}