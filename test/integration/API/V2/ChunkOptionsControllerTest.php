<?php

class ChunkOptionsControllerTest extends IntegrationTest {

    function setup() {
        $this->test_data = new StdClass();
    }
    
    function test_setting_on_uncommon_language() {
        $project = $this->prepareUncommonLanguagesProject();
        $chunks = $project->getChunks();

        $test = new CurlTest() ;
        $test->path = sprintf("/api/v2/jobs/%s/%s/options", $chunks[0]->id, $chunks[0]->password);
        $test->method = 'POST';
        $test->params = array('speech2text' => false, 'tag_projection' => true, 'lexiqa' => true);

        $response = $test->getResponse();

        $json = json_decode( $response['body'] );
        $this->assertEquals( false, $json->options->speech2text );
        $this->assertEquals( false, $json->options->tag_projection );
        $this->assertEquals( false, $json->options->lexiqa );

        $model = new ChunkOptionsModel( $chunks[0] ) ;

        $options_from_database = $model->toArray() ;

        $this->assertEquals($options_from_database, (array) $json->options ) ; 
        
    }
    
    function test_setting_on_common_languages() {
        $project = $this->prepareCommonLanguagesProject();
        $chunks = $project->getChunks(); 
        
        $test = new CurlTest() ; 
        $test->path = sprintf("/api/v2/jobs/%s/%s/options", $chunks[0]->id, $chunks[0]->password); 
        $test->method = 'POST'; 
        $test->params = array('speech2text' => 'false', 'tag_projection' => true, 'lexiqa' => true); 
        
        $response = $test->getResponse(); 

        $json = json_decode( $response['body'] );
        $this->assertEquals( false, $json->options->speech2text ); 
        $this->assertEquals( true, $json->options->tag_projection );
        $this->assertEquals( true, $json->options->lexiqa );
        
        $model = new ChunkOptionsModel( $chunks[0] ) ;
        
        $options_from_database = $model->toArray() ; 
        
        $this->assertEquals($options_from_database, (array) $json->options ) ; 
    }

    function test_just_one_value_passed_is_fine() {
        $project = $this->prepareCommonLanguagesProject();
        $chunks = $project->getChunks();

        $test = new CurlTest() ;
        $test->path = sprintf("/api/v2/jobs/%s/%s/options", $chunks[0]->id, $chunks[0]->password);
        $test->method = 'POST';
        $test->params = array('lexiqa' => 'false');

        $response = $test->getResponse();

        $json = json_decode( $response['body'] );
        $this->assertEquals( false, $json->options->lexiqa );

        $model = new ChunkOptionsModel( $chunks[0] ) ;

        $options_from_database = $model->toArray() ;

        $this->assertEquals($options_from_database, (array) $json->options ) ;
    }


    private function prepareCommonLanguagesProject() {
        $upload_session = uniqid();

        $file = test_file_path('xliff/amex-test.docx.xlf') ;
        prepare_file_in_upload_folder( $file, $upload_session );

        do_file_conversion(array(
                'source_lang' => 'it-IT',
                'target_lang' => 'en-US',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session
        ));

        $response = createProjectWithUIParams( array(
                'source_language' => 'it-IT',
                'target_language' => 'en-US',
                'pretranslate_100' => 1,
                'job_subject' => 'general',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session,
                'files' => array( $file )
        ));

        $json_response = json_decode( $response['body'], TRUE );
        $project = Projects_ProjectDao::findById( $json_response['id_project'] );
        return $project ;
    }
    
    private function prepareUncommonLanguagesProject() {
        $upload_session = uniqid();

        $file = test_file_path('xliff/amex-test.docx.xlf') ;
        prepare_file_in_upload_folder( $file, $upload_session );

        do_file_conversion(array(
                'source_lang' => 'my-MM',
                'target_lang' => 'ja-JP',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session
        ));

        $response = createProjectWithUIParams( array(
                'source_language' => 'my-MM',
                'target_language' => 'ja-JP',
                'pretranslate_100' => 1,
                'job_subject' => 'general',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session,
                'files' => array( $file )
        ));

        $json_response = json_decode( $response['body'], TRUE );
        $project = Projects_ProjectDao::findById( $json_response['id_project'] );
        return $project ; 
    }

}