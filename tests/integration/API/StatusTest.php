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

        $response = json_decode ( $this->makeRequest() ) ;

        $this->assertEquals( $response->status, 'ANALYZING' );
    }

    function testStatusOnTranslated() {
        $project = integrationCreateTestProject( );

        $this->params = array(
            'id_project' => $project->id_project,
            'project_pass' => $project->project_pass
        );

        $chunksDao = new Chunks_ChunkDao( Database::obtain() ) ;
        $chunks = $chunksDao->getByProjectID( $project->id_project );

        $this->assertTrue( count($chunks) == 1);

        foreach( $chunks as $chunk ) {
            $segments = $chunk->getSegments();
            foreach( $segments as $segment) {
                integrationSetTranslation( array(
                    'id_segment' => $segment->id ,
                    'id_job' => $chunk->id,
                    'password' => $chunk->password,
                    'status' => 'translated'
                ) ) ;
            }
        }

        foreach( $chunks as $chunk ) {
            $translations = $chunk->getTranslations();
            $this->assertEquals( 3, count($translations));

            foreach( $translations as $translation) {
               $this->assertEquals('TRANSLATED', $translation->status);
            }
        }

        // iterate all segments and setTranslations
        $response = json_decode ( $this->makeRequest() ) ;

    }
}
