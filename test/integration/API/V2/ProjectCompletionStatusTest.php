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

    }

    private function submitProjectWithApiKeys() {
        $headers = array(
            "X-MATECAT-KEY: {$this->test_data->api_key->api_key}",
            "X-MATECAT-SECRET: {$this->test_data->api_key->api_secret}"
        );
        $this->test_data->project = integrationCreateTestProject( $headers );
    }

    function testsCallOnValidProject() {
        $this->prepareUserAndApiKey();

        Factory_OwnerFeature::create( array(
            'uid' => $this->test_data->user->uid,
            'feature_code' => 'project_completion'
        ));

        $this->submitProjectWithApiKeys();

        $chunks = integrationSetSegmentsTranslated( $this->test_data->project->id_project );
        foreach( $chunks as $chunk ) {
            integrationSetChunkAsComplete( $chunk );
        }

        // TODO check everything OK
        //
        $this->markTestIncomplete();
    }
}
