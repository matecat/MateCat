<?php



class ProjectCreationTest extends IntegrationTest  {

    protected $test_data ;

    public function setUp() {
        $this->test_data = new stdClass();
        $this->prepareUserAndApiKey();

        DqfTest::$dqf_password = 'fabrizio@translated.net' ;
        DqfTest::$dqf_username = 'fabrizio@translated.net' ;
        $dqfTest = new DqfTest();
        $dqfTest->addDqfCredentials( $this->test_data->user );

        $this->test_data->headers = array(
                "X-MATECAT-KEY: {$this->test_data->api_key->api_key}",
                "X-MATECAT-SECRET: {$this->test_data->api_key->api_secret}"
        );
    }

    public function test_project_is_created() {
        $upload_session = uniqid();

        $file = test_file_path('xliff/amex-test.docx.xlf') ;
        prepare_file_in_upload_folder( $file, $upload_session );

        list( $auth_cookie ) = AuthCookie::generateSignedAuthCookie(
                $this->test_data->user->email, $this->test_data->user->uid
        );

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
                'dqf' => 1,
                'files' => array( $file ),
                'cookies' => [
                        [ \INIT::$AUTHCOOKIENAME, $auth_cookie ]
                ]
        ));

        $json_response = json_decode( $response['body'], TRUE );
        $id_project = $json_response['id_project'];
        $project = Projects_ProjectDao::findById( $id_project );

        $this->assertEquals( $this->test_data->user->email, $project->id_customer ) ;

        $this->assertEquals(0, count( array_diff(
                array(Features::DQF, Features::PROJECT_COMPLETION, Features::REVIEW_IMPROVED, Features::TRANSLATION_VERSIONS),
                $project->getFeatures()->getCodes()
            ) ) ) ;
    }
}