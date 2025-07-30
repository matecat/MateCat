<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/25/16
 * Time: 1:48 PM
 */

use LQA\ChunkReviewDao;

class SplitAndMergeTest extends IntegrationTest {

    function setUp() {

        $this->test_data = new StdClass();
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

    function test_split_and_merge_keep_first_chunk_review_password() {
        $project = $this->createProject();
        $project = Projects_ProjectDao::findById( $project->id_project );

        $review_chunks = ChunkReviewDao::findByProjectId( $project->id );
        $original_password = $review_chunks[0]->review_password ;

        $chunks = $project->getChunks();
        splitJob(array(
                'id_job'       => $chunks[0]->id,
                'id_project'   => $project->id,
                'project_pass' => $project->password,
                'job_pass'     => $chunks[0]->password,
                'num_split'    => 2,
                'split_values' => array(10, 11)
        ));
        $review_chunks = ChunkReviewDao::findByProjectId( $project->id );
        $new_password = $review_chunks[0]->review_password ;

        $this->assertEquals($original_password, $new_password, 'password changed after split');

        mergeJob(array(
                'id_job'       => $chunks[0]->id,
                'id_project'   => $project->id,
                'project_pass' => $project->password,
        ));

        $review_chunks = ChunkReviewDao::findByProjectId( $project->id );
        $new_password = $review_chunks[0]->review_password ;

        $this->assertEquals($original_password, $new_password, 'password changed after merge');

    }

    /**
     * In this test we want to check the when a job is splitted
     * the record that holds the review data is update with current
     * review data for the newly created chunks.
     *
     *
     * @throws Exception
     */

    function test_split_preserves_review_data() {
        $project = $this->createProject();
        $project = Projects_ProjectDao::findById( $project->id_project );

        $review_chunks = ChunkReviewDao::findByProjectId( $project->id );
        $this->assertEquals(1, count( $review_chunks ) );

        $chunks = $project->getChunks();
        $segments = $chunks[0]->getSegments();
        $this->assertEquals(3, count( $segments ));

        foreach( $segments as $key => $segment ) {
            integrationSetTranslation( array(
                    'id_segment'  => $segment->id ,
                    'id_job'      => $chunks[0]->id,
                    'password'    => $chunks[0]->password,
                    'status'      => 'translated',
                    'translation' => 'This is translated!',
                    'propagate'   => false
            ) ) ;

            $issue_result = $this->makeIssueOnChunk($chunks[0], array(
                'segment_index' => $key
            ));
        }

        /**
         * At this point we have one issue on each segment. We want to test
         * that when a split is done, each new chunk preserves the review
         * information ( score, reviewed_words_count, is_pass ).
         */

        $review_data = ( new ChunkReviewDao() )->findChunkReviews( $chunks[0] )[ 0 ];

        $this->assertEquals(3, $review_data->penalty_points );

        splitJob(array(
                'id_job'       => $chunks[0]->id,
                'id_project'   => $project->id,
                'project_pass' => $project->password,
                'job_pass'     => $chunks[0]->password,
                'num_split'    => 2,
                'split_values' => array(10, 11)
        ));

        $chunks = $project->getChunks();
        $this->assertEquals(2, count($chunks));

        $first_chunk_review = ( new ChunkReviewDao() )->findChunkReviews( $chunks[ 0 ] )[ 0 ];
        $last_chunk_review  = ( new ChunkReviewDao() )->findChunkReviews( $chunks[ 1 ] )[ 0 ];

        $this->assertEquals(2, $first_chunk_review->penalty_points);
        $this->assertEquals(1, $last_chunk_review->penalty_points);
    }


    function test_merge_preserves_review_data() {
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
        $this->assertEquals(2, count($chunks));

        integrationSetTranslation( array(
                'id_segment'  => firstSegmentOfChunk($chunks[0])->id ,
                'id_job'      => $chunks[0]->id,
                'password'    => $chunks[0]->password,
                'status'      => 'translated',
                'translation' => 'This is translated!',
                'propagate'   => false
        ) ) ;

        integrationSetTranslation( array(
                'id_segment'  => firstSegmentOfChunk($chunks[1])->id ,
                'id_job'      => $chunks[1]->id,
                'password'    => $chunks[1]->password,
                'status'      => 'translated',
                'translation' => 'This is translated!',
                'propagate'   => false
        ) ) ;

        // TODO: this api call to create issue is not really required here.
        // it could be done with an insert in the database.

        $result = $this->makeIssueOnChunk($chunks[0]);
        $result = $this->makeIssueOnChunk($chunks[1]);

        $first_chunk_review = ( new ChunkReviewDao() )->findChunkReviews( $chunks[ 0 ] )[ 0 ];
        $last_chunk_review = ( new ChunkReviewDao() )->findChunkReviews( $chunks[ 1 ] )[ 0 ];

        $this->assertEquals(1, $first_chunk_review->penalty_points);
        $this->assertEquals(1, $last_chunk_review->penalty_points);

        // Merge and check the count is merged
        mergeJob(array(
                'id_job'       => $chunks[0]->id,
                'id_project'   => $project->id,
                'project_pass' => $project->password,
        ));

        $chunks = $project->getChunks();

        $this->assertEquals(1, count( $chunks ));

        $merged_review_record = ( new ChunkReviewDao() )->findChunkReviews( $chunks[ 0 ] )[ 0 ];

        $this->assertEquals(2, $merged_review_record->penalty_points);
    }

    private function makeIssueOnChunk( Chunks_ChunkStruct $chunk, $options=array()) {
        $project = $chunk->getProject();
        $categories = $project->getLqaModel()->getCategories();
        $severities = $categories[0]->getJsonSeverities();

        $options = array_merge( array(
                'severity'      => 'Minor',
                'segment_index' => 0
        ), $options );

        // register some issue generate review data in each chunk
        $issue_data = array(
                "id_category"  => $categories[0]->id,
                'severity'     => $options['severity'],
                'target_text'  => 'whatever',
                'start_node'   => 0,
                'start_offset' => 3,
                'end_node'     => 0,
                'end_offset'   => 8,
                'comment'      => ''
        );

        // Issue on first chunk
        $id_job = $chunk->id;
        $password = $chunk->password;
        $segments = $chunk->getSegments();
        $id_segment = $segments[ $options['segment_index'] ]->id;

        $issue_request = new CurlTest(array(
                'path' => "/api/v2/jobs/$id_job/$password/segments/$id_segment/translation-issues",
                'params' => $issue_data,
                'referer' => '/translate/foo/bar',
                'method' => 'POST'
        ));

        $issue_request->run();

        return $issue_request ;
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