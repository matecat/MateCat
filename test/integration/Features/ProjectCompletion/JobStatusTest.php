<?php

class Features_ProjectCompletion_JobStatusTest extends IntegrationTest {

    function setup() {

    }

    function testsNoResponseIfFeatureNotEnabled() {
        $this->params = array(
            'id_job' => 1
        );

        $this->markTestIncomplete();
    }

    function testsJSONForUncompletedJob() {
      $this->markTestIncomplete();
    }

    function testsJSONForCompletedJob() {
      $this->markTestIncomplete();
    }

    function testsSubmitCompletedStatus() {
      $this->markTestIncomplete();
    }

    function testSubmitOnNonTranslatedProject() {
      $this->markTestIncomplete();
    }
}
