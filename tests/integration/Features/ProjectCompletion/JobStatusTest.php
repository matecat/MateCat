<?php

class Features_ProjectCompletion_JobStatusTest extends IntegrationTest {

    function setup() {
    }

    function testsNoResponseIfFeatureNotEnabled() {
        $this->params = array(
            'id_job' => 1
        );
    }

    function testsJSONForUncompletedJob() {
    }

    function testsJSONForCompletedJob() {
    }

    function testsSubmitCompletedStatus() {
    }
}
