<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/02/16
 * Time: 18.00
 *
 */
class Converters_ConversionsTest extends AbstractTest {


    /**
     * @skip
     */
    function testSingleFileConversion() {

        // Stop here and mark this test as incomplete.
        $this->markTestIncomplete(
                'This test has not been implemented yet.'
        );

        $options = array('files' => array(
                test_file_path('doc/WhiteHouse.doc')
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
        $this->assertEquals( 'This is a comment', $notes_segment_one[0]->note);
        $this->assertEquals( 'This is another comment for the same segment', $notes_segment_one[1]->note);
        $this->assertEquals( 'This is another comment', $notes_segment_two[0]->note);
        $this->assertEquals( 0,  count( $segments[2]->getNotes()));
        $this->assertEquals( 0,  count( $segments[3]->getNotes()));

    }

}