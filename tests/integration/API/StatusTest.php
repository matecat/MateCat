<?php

class StatusTest extends IntegrationTest {

    function setup() {
        $this->path = '/api/status' ;
        $this->method = 'GET';

        parent::setup();
    }

    function testsRequiredProjectIdAndPassword() {
        $expected = array(
            'status' => 'FAIL',
            "message" => [-1,"No id project provided"]
        );

        $response = $this->makeRequest();
        $this->assertJSONResponse( $expected, $response );
    }

    function testsStatusOnNewProject() {
      $project = integrationCreateTestProject( );

      $this->params = array(
        'id_project' => $project->id_project,
        'project_pass' => $project->project_pass
      );

      $expected = array(
        'foo' => 'bar'
      );

      $response = json_decode ( $this->makeRequest() ) ;

      $this->assertEquals( $response->status, 'ANALYZING' );
    }
}
