<?php

class Features_ProjectCompletion_JobStatusTest extends IntegrationTest {

    function setup() {

    }

    function testsNoResponseIfFeatureNotEnabled() {
        $this->params = array(
            'id_job' => 1
        );

        $this->markTestSkipped();
    }

    function testsJSONForUncompletedJob() {
      $this->markTestSkipped();
    }

    function testsJSONForCompletedJob() {
      $this->markTestSkipped();
    }

    function testsSubmitCompletedStatus() {
      $this->markTestSkipped();
    }

    function testSubmitOnNonTranslatedProject() {
      $this->markTestSkipped();
    }
}
