<?php

class Features_SegmentNotesCreationTest extends AbstractTest {

    function testRecordsAreCreatedWithProject() {

        $this->markTestSkipped("This test Fails because of Filters. Created XLF has --- between merged notes. Seems that only one note per trans-unit is allowed." );

        $options = array('files' => array(
            test_file_path('small-with-notes.sdlxliff')
        ));

        $body = integrationCreateTestProject($options);

        $this->assertNotNull($body->id_project);
        $this->assertNotNull($body->project_pass);

        // ensure one job is created
        $project = Projects_ProjectDao::findById($body->id_project);

        $this->assertEquals(1, count($project->getJobs()));

        $jobs = $project->getJobs() ;
        $chunks = $jobs[0]->getChunks() ;
        $segments = $chunks[0]->getSegments();
        $notes_segment_one = $segments[0]->getNotes() ;
        $notes_segment_two = $segments[1]->getNotes() ;

        $this->assertEquals( 4, count($segments) );
        $this->assertEquals( 'This is a comment', $notes_segment_one[0]->note, "This assertion Fails because of Filters. Created XLF has --- between merged notes. Seems that only one note per trans-unit is allowed." );
        $this->assertEquals( 'This is another comment for the same segment', $notes_segment_one[1]->note);
        $this->assertEquals( 'This is another comment', $notes_segment_two[0]->note);
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

        $chunks   = $jobs[0]->getChunks() ;
        $segments = $chunks[0]->getSegments();
        $notes    = $segments[0]->getNotes() ;

        $this->assertEquals( 3, count($segments));

        $this->assertEquals(
            "This is a comment\n" .
            "---\n" .
            "This is a comment number two\n" .
            "---\n" .
            "This is a comment number three",

            $notes[0]->note
        );
    }

    function testNoteIsAssignedToAllMrkInTransUnit() {
        $options = array('files' => array(
            test_file_path('xliff/sdlxliff-with-mrk-and-note.xlf.sdlxliff')
        ));

        $body = integrationCreateTestProject($options);

        $this->assertNotNull($body->id_project);
        $this->assertNotNull($body->project_pass);

        // ensure one job is created
        $project = Projects_ProjectDao::findById($body->id_project);

        $jobs = $project->getJobs();

        // expect one job is created
        $this->assertEquals(1, count($project->getJobs()));

        $chunks   = $jobs[0]->getChunks() ;
        $segments = $chunks[0]->getSegments();

        // expect one segment per mrk
        $this->assertEquals( 2, count($segments));

        // expect the correct notes count
        $this->assertEquals( 1, count( $segments[0]->getNotes() ) );
        $this->assertEquals( 1, count( $segments[1]->getNotes() ) );

        // expect the same note text
        $notes_segment_one = $segments[0]->getNotes();
        $notes_segment_two = $segments[1]->getNotes();

        $this->assertEquals('Test note', $notes_segment_one[0]->note );
        $this->assertEquals('Test note', $notes_segment_two[0]->note );

        // expect the internal_id is saved appropriately
        $this->assertEquals(
            $notes_segment_one[0]->internal_id,
            $notes_segment_two[0]->internal_id
        );

    }

}
