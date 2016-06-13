<?php

class createProjectControllerTest extends IntegrationTest {

    private $test_data=array();

    function setUp() {
        $this->test_data=new stdClass();
    }

    function test_lexiqa_to_project_metadata() {
        $upload_session = 'abc' ; 
        
        $file = test_file_path('xliff/amex-test.docx.xlf') ; 
        prepare_file_in_upload_folder( $file, $upload_session );

        do_file_conversion(array(
                'source_lang' => 'en',
                'target_lang' => 'it',
                'file_name' => 'amex-test.docx.xlf',
                'upload_session' => $upload_session
        )); 
        
        $curlTest = new CurlTest(); 
        
        $curlTest->path = '/index.php?action=createProject' ;
        $curlTest->params = array(
                'lexiqa' => true,
                'source_language' => 'en',
                'target_language' => 'it',
                'pretranslate_100' => 1,
                'job_subject' => 'general',
                'file_name' => 'amex-test.docx.xlf', 
        );

        $curlTest->cookies[] = array('upload_session', $upload_session ); 
        $curlTest->files[] = $file ; 
        
        $response = $curlTest->getResponse();
        
        $json_response = json_decode( $response['body'], TRUE ); 
        
        $id_project = $json_response['id_project']; 
        
        $project = Projects_ProjectDao::findById( $id_project );
        $this->assertNotNull( $project->id );
        $metadata = $project->getMetadataAsKeyValue();

        $this->assertEquals( '1', $metadata['lexiqa'] );
    }

    function test_tag_projection_to_project_metadata() {
        $project_response = integrationCreateTestProject( array(
                'params'  => array(
                        'tag_projection' => true, 
                ), 
        ));

        $project = Projects_ProjectDao::findById( $project_response->id_project );
        $this->assertEquals( $project->name, 'test project' ) ; 
        
        $this->assertNotNull( $project->id );
        $metadata = $project->getMetadataAsKeyValue();
        
        var_dump( $metadata ); 

        $this->assertTrue( $metadata['tag_projection'] );
    }
    
    function test_speech2text_to_project_metadata() {
        $project_response = integrationCreateTestProject( array(
                'params'  => array('speech2text' => true)
        ));

        $project = Projects_ProjectDao::findById( $project_response->id_project );
        $this->assertNotNull( $project->id );
        $metadata = $project->getMetadataAsKeyValue();
        
        $this->assertTrue( $metadata['speech2text'] ); 
    }

}
    
    


