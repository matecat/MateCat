<?php
class ProjectCompletionStatusTest extends IntegrationTest {

    function setup() {
        $this->test_data = new StdClass();
    }

    private function prepareUserAndApiKey() {
        $this->test_data->user  = Factory_User::create() ;
        $this->test_data->api_key = Factory_ApiKey::create(array(
            'uid' => $this->test_data->user->uid,
        ));

        $this->test_data->headers = array(
            "X-MATECAT-KEY: {$this->test_data->api_key->api_key}",
            "X-MATECAT-SECRET: {$this->test_data->api_key->api_secret}"
        );

    }

    private function submitProjectWithApiKeys() {
        $this->test_data->project = integrationCreateTestProject(
            array('headers' => $this->test_data->headers)
        );
    }

    private function setValidProjectWithAllTranslatedSegments() {
        $this->prepareUserAndApiKey();
        Factory_OwnerFeature::create( array(
            'uid' => $this->test_data->user->uid,
            'feature_code' => 'project_completion'
        ));

        $this->submitProjectWithApiKeys();

        $this->test_data->chunks = integrationSetSegmentsTranslated(
            $this->test_data->project->id_project
        );
    }

    function testsCallOnValidProject() {
        $this->setValidProjectWithAllTranslatedSegments();

        foreach( $this->test_data->chunks as $chunk ) {
            integrationSetChunkAsComplete( array(
                'params' => array(
                    'id_job' => $chunk->id,
                    'password' => $chunk->password
                )
            ));
        }

        $test = new CurlTest();
        $test->path = '/api/v2/project-completion-status/' .
            $this->test_data->project->id_project  ;
        $test->method = 'GET';
        $test->headers = $this->test_data->headers ;

        $response = $test->getResponse();
        $expected = array(
            'project_status' => 'completed',
        );

        $this->assertEquals( json_encode($expected), $response['body'] );

    }

    function testReturnsNonCompletedProject() {
        $this->setValidProjectWithAllTranslatedSegments();

        // foreach( $this->test_data->chunks as $chunk ) {
        //     integrationSetChunkAsComplete( array(
        //         'params' => array(
        //             'id_job' => $chunk->id,
        //             'password' => $chunk->password
        //         )
        //     ));
        // }

        $test = new CurlTest();
        $test->path = '/api/v2/project-completion-status/' .
            $this->test_data->project->id_project  ;
        $test->method = 'GET';
        $test->headers = $this->test_data->headers ;

        $response = $test->getResponse();
        $expected = array(
            'project_status' => 'not completed',
        );

        $this->assertEquals( json_encode($expected), $response['body'] );

    }


}
