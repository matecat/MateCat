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
        
        $options = array(
                'files' => array(
                        test_file_path( 'docx/WhiteHouse.docx' )
                )
        );

        $body = integrationCreateTestProject( $options );

        $this->assertNotNull( $body->id_project );
        $this->assertNotNull( $body->project_pass );

        // ensure one job is created
        $project = Projects_ProjectDao::findById( $body->id_project );

        $this->assertEquals( 1, count( $project->getJobs() ) );

        $jobs              = $project->getJobs();
        $chunks            = $jobs[ 0 ]->getChunks();
        $segments          = $chunks[ 0 ]->getSegments();
        $notes_segment_one = $segments[ 0 ]->getNotes();

        $this->assertNotEmpty( $jobs );
        $this->assertNotEmpty( $chunks );
        $this->assertEquals( 11, count( $segments ) );
        $this->assertEmpty( $notes_segment_one );

    }

}