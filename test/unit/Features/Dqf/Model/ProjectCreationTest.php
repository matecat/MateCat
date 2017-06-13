<?php

use Features\Dqf\Service\Struct\ProjectCreationStruct ;
use Features\Dqf\Model\ProjectCreation;

class ProjectCreationTest extends PHPUnit_Framework_TestCase  {

    public function setUp() {
        TestHelper::resetDb();
    }

    public function testProjectIsCreated() {
        $pid = TestHelper::$FIXTURES->getFixtures()['projects']['dqf_project_1']['id'] ;
        $project = Projects_ProjectDao::findById( $pid );

        $this->assertEquals( $this->test_data->user->email, $project->id_customer ) ;

        $this->assertEquals(0, count( array_diff(
                array(Features::DQF, Features::PROJECT_COMPLETION,
                        Features::REVIEW_IMPROVED, Features::TRANSLATION_VERSIONS),
                $project->getFeatures()->getCodes()
            ) ) ) ;

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
}