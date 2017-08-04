<?php

use Features\Dqf\Model\ChildProjectTranslationBatch;
use Features\Dqf\Service\Struct\ProjectCreationStruct ;

use Features\Dqf\Model\ProjectCreation ;

class ProjectCreationTest extends PHPUnit_Framework_TestCase  {

    /**
     * These tests are making actual API calls to TAUS. We use these tests to ensure
     * communication doesn't break. This is why there are little or no assertions.
     */
    public function setUp() {
        TestHelper::resetDb();
    }

    public function testProjectCreationDoesNotBreak() {

        $pid = TestHelper::$FIXTURES->getFixtures()['projects']['dqf_project_1']['id'] ;
        $project = Projects_ProjectDao::findById( $pid );

        $this->assertEquals( 'fabrizio@translated.net', $project->id_customer ) ;

        // Simulate the initial process of this project
        $files = $project->getChunks()[0]->getFiles() ;

        // find number of segments per file
        $struct = new ProjectCreationStruct([
                'id_project' =>  $pid,
                'source_language' => 'en-US',
                'file_segments_count' => [
                        $files[0]->id => $files[0]->getSegmentsCount()
                ]
        ]);

        $model = new ProjectCreation( $struct );
        $model->process() ;
    }

    public function testProjectCreationAndSubmitTranslationBatch() {

        // PRECONDITION ---
        $pid = TestHelper::$FIXTURES->getFixtures()['projects']['dqf_project_1']['id'] ;
        $project = Projects_ProjectDao::findById( $pid );

        $this->assertEquals( 'fabrizio@translated.net', $project->id_customer ) ;

        // Simulate the initial process of this project
        $files = $project->getChunks()[0]->getFiles() ;

        // find number of segments per file
        $struct = new ProjectCreationStruct([
                'id_project' =>  $pid,
                'source_language' => 'en-US',
                'file_segments_count' => [
                        $files[0]->id => $files[0]->getSegmentsCount()
                ]
        ]);

        $model = new ProjectCreation( $struct );
        $model->process() ;

        // TEST START ---
        // when submitting a batch, the user must be read from job_metadata
        $chunk = $project->getChunks()[0] ;
        $translationBatch = new ChildProjectTranslationBatch( $chunk );
        $translationBatch->process();
    }

}