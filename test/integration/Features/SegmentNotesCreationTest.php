<?php

class Features_SegmentNotesCreationTest extends AbstractTest
{


    function testRecordsAreCreatedWithProject()
    {
        $options = array('files' => array(
            test_file_path('small-with-notes.sdlxliff')
        ));

        $body = integrationCreateTestProject($options);

        $this->assertNotNull($body->id_project);
        $this->assertNotNull($body->project_pass);

        // ensure one job is created
        $project = Projects_ProjectDao::findById($body->id_project);

        $jobs = $project->getJobs(); 
        $this->assertEquals(1, count($project->getJobs()));

        $segments = $project->getJobs()[0]->getChunks()[0]->getSegments(); 
        $this->assertEquals( 4, count($segments)); 

        $this->assertEquals( 'This is a comment', $segments[0]->getNotes()[0]->note);
        $this->assertEquals( 'This is another comment for the same segment', $segments[0]->getNotes()[1]->note);

        $this->assertEquals( 'This is another comment', $segments[1]->getNotes()[0]->note);

        $this->assertEquals( 0,  count( $segments[2]->getNotes()));
        $this->assertEquals( 0,  count( $segments[3]->getNotes()));

    }

    function testXliffWithSegSource() {
        $options = array('files' => array(
            test_file_path('xliff/file-with-notes-converted.xliff')
        ));

        $body = integrationCreateTestProject($options);

        $this->assertNotNull($body->id_project);
        $this->assertNotNull($body->project_pass);

        // ensure one job is created
        $project = Projects_ProjectDao::findById($body->id_project);

        $jobs = $project->getJobs();
        $this->assertEquals(1, count($project->getJobs()));

        $segments = $project->getJobs()[0]->getChunks()[0]->getSegments();
        $this->assertEquals( 3, count($segments));

        $this->assertEquals(
            "This is a comment\n" .
            "---\n" .
            "This is a comment number two\n" .
            "---\n" .
            "This is a comment number three",

            $segments[0]->getNotes()[0]->note
        );


    }

}

