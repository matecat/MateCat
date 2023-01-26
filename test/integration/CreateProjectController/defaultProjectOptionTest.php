<?php

class createProjectControllerTest extends IntegrationTest {

    function setUp() {
        $this->test_data=new stdClass();
    }

    function test_speech2text_is_enabled_by_default() {
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
                'files' => array( $file )
        )); 

        $json_response = json_decode( $response['body'], TRUE );

        $id_project = $json_response['id_project'];

        $project = Projects_ProjectDao::findById( $id_project );
        $this->assertNotNull( $project->id );
        $metadata = $project->getMetadataAsKeyValue();

        $this->assertEquals( false, array_key_exists('speech2text', $metadata ) );
    }

    function test_speech2text_can_be_disabled() {
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
                'speech2text' => 0, 
                'source_language' => 'en-US',
                'target_language' => 'it-IT',
                'pretranslate_100' => 1,
                'job_subject' => 'general',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session,
                'files' => array( $file )
        ));

        $json_response = json_decode( $response['body'], TRUE );

        $id_project = $json_response['id_project'];

        $project = Projects_ProjectDao::findById( $id_project );
        $this->assertNotNull( $project->id );
        $metadata = $project->getMetadataAsKeyValue();
        
        $this->assertFalse( !!$metadata['speech2text'] );
    }

    function test_lexiqa_is_enabled_if_language_combination_is_ok() {
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
                'lexiqa' => 'true'
        ));

        $json_response = json_decode( $response['body'], TRUE );

        $id_project = $json_response['id_project'];

        $project = Projects_ProjectDao::findById( $id_project );
        $this->assertNotNull( $project->id );
        $metadata = $project->getMetadataAsKeyValue();

        $this->assertEquals( '1', $metadata['lexiqa'] );
    }

    function test_lexiqa_is_disabled_if_language_combination_is_not_ok() {
        $upload_session = uniqid();

        $file = test_file_path('xliff/amex-test.docx.xlf') ;
        prepare_file_in_upload_folder( $file, $upload_session );

        do_file_conversion(array(
                'source_lang' => 'my-MM',
                'target_lang' => 'ja-JP,be-BY',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session
        ));

        $response = createProjectWithUIParams( array(
                'source_language' => 'my-MM',
                'target_language' => 'ja-JP,be-BY', 
                'pretranslate_100' => 1,
                'job_subject' => 'general',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session,
                'files' => array( $file ),
                'lexiqa' => 'true'
        ));

        $json_response = json_decode( $response['body'], TRUE );

        $id_project = $json_response['id_project'];

        $project = Projects_ProjectDao::findById( $id_project );
        $this->assertNotNull( $project->id );
        $metadata = $project->getMetadataAsKeyValue();

        $this->assertFalse( !!$metadata['lexiqa'] ); 
    }

    function test_lexiqa_is_enabled_if_one_language_is_in_list() {
        $upload_session = uniqid();

        $file = test_file_path('xliff/amex-test.docx.xlf') ;
        prepare_file_in_upload_folder( $file, $upload_session );

        do_file_conversion(array(
                'source_lang' => 'en-US',
                'target_lang' => 'it-IT,be-BY',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session
        ));

        $response = createProjectWithUIParams( array(
                'source_language' => 'en-US',
                'target_language' => 'it-IT,be-BY',
                'pretranslate_100' => 1,
                'job_subject' => 'general',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session,
                'files' => array( $file ),
                'lexiqa' => 'true'
        ));

        $json_response = json_decode( $response['body'], TRUE );

        $id_project = $json_response['id_project'];

        $project = Projects_ProjectDao::findById( $id_project );
        $this->assertNotNull( $project->id );
        $metadata = $project->getMetadataAsKeyValue();

        $this->assertEquals( '1', $metadata['lexiqa'] ); 
        
    }

    function test_lexiqa_can_be_disabled() {
        $upload_session = uniqid();

        $file = test_file_path('xliff/amex-test.docx.xlf') ;
        prepare_file_in_upload_folder( $file, $upload_session );

        do_file_conversion(array(
                'source_lang' => 'my-MM',
                'target_lang' => 'it-IT,be-BY',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session
        ));

        $response = createProjectWithUIParams( array(
                'lexiqa' => 0, 
                'source_language' => 'my-MM',
                'target_language' => 'it-IT,be-BY',
                'pretranslate_100' => 1,
                'job_subject' => 'general',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session,
                'files' => array( $file )
        ));

        $json_response = json_decode( $response['body'], TRUE );

        $id_project = $json_response['id_project'];

        $project = Projects_ProjectDao::findById( $id_project );
        $this->assertNotNull( $project->id );
        $metadata = $project->getMetadataAsKeyValue();

        $this->assertFalse( !!$metadata['lexiqa'] ); 
    }

    function test_tag_projection_is_enabled_if_language_combination_is_ok() {
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
                'tag_projection' => 'true'
        ));

        $json_response = json_decode( $response['body'], TRUE );

        $id_project = $json_response['id_project'];

        $project = Projects_ProjectDao::findById( $id_project );
        $this->assertNotNull( $project->id );
        $metadata = $project->getMetadataAsKeyValue();

        $this->assertEquals( '1', $metadata['tag_projection'] );
    }

    function test_tag_projection_is_disabled_if_language_combination_is_not_ok() {
        $upload_session = uniqid();

        $file = test_file_path('xliff/amex-test.docx.xlf') ;
        prepare_file_in_upload_folder( $file, $upload_session );

        do_file_conversion(array(
                'source_lang' => 'my-MM',
                'target_lang' => 'ja-JP,be-BY',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session
        ));

        $response = createProjectWithUIParams( array(
                'source_language' => 'my-MM',
                'target_language' => 'ja-JP,be-BY',
                'pretranslate_100' => 1,
                'job_subject' => 'general',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session,
                'files' => array( $file ),
                'tag_projection' => 'true'
        ));

        $json_response = json_decode( $response['body'], TRUE );

        $id_project = $json_response['id_project'];

        $project = Projects_ProjectDao::findById( $id_project );
        $this->assertNotNull( $project->id );
        $metadata = $project->getMetadataAsKeyValue();

        $this->assertFalse( !!$metadata['tag_projection'] );
    }

    function test_tag_projection_is_enabled_if_one_language_is_in_list() {
        $upload_session = uniqid();

        $file = test_file_path('xliff/amex-test.docx.xlf') ;
        prepare_file_in_upload_folder( $file, $upload_session );

        do_file_conversion(array(
                'source_lang' => 'en-US',
                'target_lang' => 'it-IT,be-BY',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session
        ));

        $response = createProjectWithUIParams( array(
                'source_language' => 'en-US',
                'target_language' => 'it-IT,be-BY',
                'pretranslate_100' => 1,
                'job_subject' => 'general',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session,
                'files' => array( $file ),
                'tag_projection' => 'true'
        ));

        $json_response = json_decode( $response['body'], TRUE );

        $id_project = $json_response['id_project'];

        $project = Projects_ProjectDao::findById( $id_project );
        $this->assertNotNull( $project->id );
        $metadata = $project->getMetadataAsKeyValue();

        $this->assertEquals( '1', $metadata['tag_projection'] );

    }

    function test_tag_projection_can_be_disabled() {
        $upload_session = uniqid();

        $file = test_file_path('xliff/amex-test.docx.xlf') ;
        prepare_file_in_upload_folder( $file, $upload_session );

        do_file_conversion(array(
                'source_lang' => 'my-MM',
                'target_lang' => 'it-IT,be-BY',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session
        ));

        $response = createProjectWithUIParams( array(
                'tag_projection' => 0,
                'source_language' => 'my-MM',
                'target_language' => 'it-IT,be-BY',
                'pretranslate_100' => 1,
                'job_subject' => 'general',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session,
                'files' => array( $file ),
                'tag_projection' => 'false'
        ));

        $json_response = json_decode( $response['body'], TRUE );

        $id_project = $json_response['id_project'];

        $project = Projects_ProjectDao::findById( $id_project );
        $this->assertNotNull( $project->id );
        $metadata = $project->getMetadataAsKeyValue();

        $this->assertEquals( FALSE, !!$metadata['tag_projection'] );
    }

}
    
    


