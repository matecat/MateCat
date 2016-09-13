<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 13/09/16
 * Time: 09:41
 */
class ProjectUrlsTest extends AbstractTest
{

    function test_should_get_urls_from_api() {

        $project = integrationCreateTestProject();

        $this->params = array(
            'id_project' => $project->id_project,
            'project_pass' => $project->project_pass
        );

        $test          = new CurlTest();
        $test->path    = sprintf("/api/v2/projects/%s/%s/urls",
            $project->id_project, $project->project_pass
        );

        $response = $test->getResponse();
        $this->assertEquals( 200, $response['code'] );

        // TODO: check it contains all required urls
    }
}