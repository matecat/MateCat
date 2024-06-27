<?php

use \LQA\ChunkReviewDao ;

class CreateRecordInQaJobReviewsTest extends IntegrationTest {

    private $test_data=array();
    function setUp() {
        $this->test_data=new stdClass();
        $this->test_data->user = Factory_User::create();

        $feature = Factory_OwnerFeature::create( array(
            'uid'          => $this->test_data->user->uid,
            'feature_code' => Features::REVIEW_IMPROVED
        ) );

        $this->test_data->api_key = Factory_ApiKey::create( array(
            'uid' => $this->test_data->user->uid,
        ) );

        $this->test_data->headers = array(
            "X-MATECAT-KEY: {$this->test_data->api_key->api_key}",
            "X-MATECAT-SECRET: {$this->test_data->api_key->api_secret}"
        );

    }

    function tests_qa_job_reivew_record_is_present() {
        $project = $this->createProject();
        $project = Projects_ProjectDao::findById( $project->id_project );

        $this->assertNotNull( $project->id );
        $reviews = ChunkReviewDao::findByProjectId( $project->id ) ;
        $this->assertNotEmpty( $reviews );
    }


    function tests_qa_job_review_record_for_multiple_targets() {
        // Create a project with multilanguage target

        $project = integrationCreateTestProject( array(
            'headers' => $this->test_data->headers,
            'files' => array(
                test_file_path('zip-with-model-json.zip')
            ),
            'params' => array(
                'target_lang' => 'it-IT,pt-BR'
            )
        ));

        // $project = $this->createProject();
        $project = Projects_ProjectDao::findById( $project->id_project );

        $this->assertNotNull( $project->id );
        $reviews = ChunkReviewDao::findByProjectId( $project->id ) ;
        $this->assertCount(2, $reviews) ;
        $jobs = $project->getJobs();
        $this->assertNotNull( ChunkReviewDao::findByIdJob( $jobs[0]->id ) );
        $this->assertNotNull( ChunkReviewDao::findByIdJob( $jobs[1]->id ) );
    }

    function tests_update_chunk_review_records_on_split() {
        $project = $this->createProject();
        $project = Projects_ProjectDao::findById( $project->id_project );

        $chunks = $project->getChunks();

        splitJob(array(
            'id_job'       => $chunks[0]->id,
            'id_project'   => $project->id,
            'project_pass' => $project->password,
            'job_pass'     => $chunks[0]->password,
            'num_split'    => 2,
            'split_values' => array(10, 11)
        ));

        $chunks = $project->getChunks();
        $this->assertEquals(2, count( $chunks ) );

        $review_chunks = ChunkReviewDao::findByProjectId( $project->id );
        $this->assertEquals(2, count( $review_chunks ) );
    }

    function tests_update_chunk_review_records_on_merge() {
        $project = $this->createProject();
        $project = Projects_ProjectDao::findById( $project->id_project );

        $review_chunks = ChunkReviewDao::findByProjectId( $project->id );
        $this->assertEquals(1, count( $review_chunks ) );

        $chunks = $project->getChunks();

        splitJob(array(
            'id_job'       => $chunks[0]->id,
            'id_project'   => $project->id,
            'project_pass' => $project->password,
            'job_pass'     => $chunks[0]->password,
            'num_split'    => 2,
            'split_values' => array(10, 11)
        ));

        $chunks = $project->getChunks();
        $this->assertEquals(2, count( $chunks ) );

        $review_chunks = ChunkReviewDao::findByProjectId( $project->id );
        $this->assertEquals(2, count( $review_chunks ) );

        // Now merge the job again
        mergeJob(array(
            'id_job'       => $chunks[0]->id,
            'id_project'   => $project->id,
            'project_pass' => $project->password,
        ));

        $chunks = $project->getChunks();
        $this->assertEquals(1, count( $chunks ) );

        $review_chunks = ChunkReviewDao::findByProjectId( $project->id );
        $this->assertEquals(1, count( $review_chunks ) );
    }

    private function createProject() {
        return integrationCreateTestProject( array(
            'headers' => $this->test_data->headers,
            'files' => array(
                test_file_path('zip-with-model-json.zip')
            )
        ));
    }
}
