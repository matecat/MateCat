<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 2/11/16
 * Time: 8:51 PM
 */

namespace Features\ReviewExtended\Model;

use ArrayObject;
use Chunks_ChunkCompletionEventDao;
use Chunks_ChunkStruct;
use Database;
use Exception;
use Features\ReviewExtended\IChunkReviewModel;
use Features\ReviewExtended\ReviewUtils;
use LQA\ChunkReviewDao;
use Revise\FeedbackDAO;
use RevisionFactory;
use Users_UserDao;


class QualityReportModel {

    /**
     * @var Chunks_ChunkStruct
     */
    protected $chunk;

    protected $quality_report_structure = [];

    private $current_file = [];

    private $current_segment = [];

    private $current_issue = [];

    private $chunk_review;

    /**
     * @var IChunkReviewModel
     */
    private $chunk_review_model;

    private $all_segments = [];

    private $date_format;

    private $avg_time_to_edit;
    private $avg_edit_distance;

    private $version;

    /**
     * @param Chunks_ChunkStruct $chunk
     */
    public function __construct( Chunks_ChunkStruct $chunk ) {
        $this->chunk = $chunk;
    }

    public function getChunk() {
        return $this->chunk;
    }

    public function getProject() {
        return $this->chunk->getProject();
    }

    public function getStructure() {
        $records = QualityReportDao::getSegmentsForQualityReport( $this->chunk );

        return $this->buildQualityReportStructure( $records );
    }

    public function getChunkReview() {
        if ( $this->chunk_review == null ) {
            $this->chunk_review = ( new ChunkReviewDao() )->findChunkReviews( $this->chunk )[ 0 ];
        }

        return $this->chunk_review;
    }

    public function getScore() {
        return number_format( $this->getChunkReviewModel()->getScore(), 2, '.', ',' );
    }

    public function isPass() {
        return (bool)$this->getChunkReview()->is_pass;
    }

    public function getChunkReviewModel() {
        if ( $this->chunk_review_model == null ) {
            $this->chunk_review_model = RevisionFactory::initFromProject( $this->getProject() )->getChunkReviewModel( $this->getChunkReview() );
        }

        return $this->chunk_review_model;
    }

    public function resetScore( $event_id ) {
        $chunkReview            = $this->getChunkReview();
        $chunkReview->undo_data = json_encode( [
                'reset_by_event_id'    => $event_id,
                'penalty_points'       => $chunkReview->penalty_points,
                'reviewed_words_count' => $chunkReview->reviewed_words_count,
                'is_pass'              => $chunkReview->is_pass
        ] );

        $chunkReview->penalty_points       = 0;
        $chunkReview->reviewed_words_count = 0;
        $chunkReview->is_pass              = 1;

        ChunkReviewDao::updateStruct( $chunkReview );
    }

    /**
     * @param $format
     */
    public function setDateFormat( $format ) {
        $this->date_format = $format;
    }

    private function __setAverages() {
        $dao  = new QualityReportDao();
        $avgs = $dao->getAverages( $this->getChunk() );

        $this->avg_edit_distance = round( $avgs[ 'avg_edit_distance' ] / 1000, 2 );
        $this->avg_time_to_edit  = round( $avgs[ 'avg_time_to_edit' ] / 1000, 2 );
    }

    /**
     * @param $records
     *
     * @return array
     */
    protected function buildQualityReportStructure( $records ) {
        $this->__setAverages();
        $this->quality_report_structure = [
                'chunk'   => [
                        'files'             => [],
                        'avg_time_to_edit'  => $this->avg_time_to_edit,
                        'avg_edit_distance' => $this->avg_edit_distance
                ],
                'job'     => [
                        'source' => $this->chunk->getJob()->source,
                        'target' => $this->chunk->getJob()->target,
                ],
                'project' => [
                        'metadata'   => $this->getProject()->getMetadataAsKeyValue(),
                        'id'         => $this->getProject()->id,
                        'created_at' => $this->filterDate(
                                $this->getProject()->create_date
                        )
                ]
        ];

        $this->buildFilesSegmentsNestedTree( $records );
        $this->_attachReviewsData();

        return $this->quality_report_structure;
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function _attachReviewsData() {
        $chunk_reviews = ( new \Features\ReviewExtended\Model\ChunkReviewDao() )->findChunkReviews( $this->chunk );

        $this->quality_report_structure[ 'chunk' ][ 'reviews' ] = [];
        foreach ( $chunk_reviews as $chunk_review ) {

            // try to load Revision Extended but should not load the Improved ( deprecated )
            $chunkReviewModel = RevisionFactory::initFromProject( $this->getProject() )->getChunkReviewModel( $chunk_review );

            $revisionNumber = ReviewUtils::sourcePageToRevisionNumber( $chunk_review->source_page );
            $feedback       = ( new FeedbackDAO() )->getFeedback( $this->chunk->id, $chunk_review->review_password, $revisionNumber );

            $this->quality_report_structure[ 'chunk' ][ 'reviews' ][] = [
                    'revision_number' => $revisionNumber,
                    'feedback'        => ( $feedback and isset( $feedback[ 'feedback' ] ) ) ? $feedback[ 'feedback' ] : null,
                    'is_pass'         => !!$chunk_review->is_pass,
                    'score'           => $chunkReviewModel->getScore(),
                    'reviewer_name'   => $this->getReviewerName()
            ];
        }
    }

    /**
     * @return string
     */
    protected function getReviewerName() {
        $completion_event = Chunks_ChunkCompletionEventDao::lastCompletionRecord(
                $this->chunk, [ 'is_review' => true ]
        );
        $name             = '';

        if ( !empty( $completion_event ) && isset( $completion_event[ 'uid' ] ) ) {
            $userDao = new Users_UserDao( Database::obtain() );
            $user    = $userDao->getByUid( $completion_event[ 'uid' ] );
            $name    = $user->fullName();
        }

        return $name;
    }

    public function getAllSegments() {
        return $this->all_segments;
    }

    private function buildFilesSegmentsNestedTree( $records ) {
        $current_file_id    = null;
        $current_segment_id = null;
        $current_issue_id   = null;

        foreach ( $records as $record ) {

            if ( $current_file_id != $record[ 'file_id' ] ) {
                $this->structureNestFile( $record );
            }

            if ( $current_segment_id != $record[ 'segment_id' ] ) {
                $this->structureNestSegment( $record );
            }

            if ( $current_issue_id != $record[ 'issue_id' ] && $record[ 'issue_id' ] !== null ) {
                $this->structureNestIssue( $record );
            }

            if ( $record[ 'comment_id' ] != null ) {
                $this->structureNestComment( $record );
            }

            if ( isset( $record[ 'warning_scope' ] ) && $record[ 'warning_scope' ] != null ) {
                $this->structureNestQaChecks( $record ); // ache serve sto coso?
            }

            $current_file_id    = $record[ 'file_id' ];
            $current_segment_id = $record[ 'segment_id' ];
            $current_issue_id   = $record[ 'issue_id' ];
        }

    }

    private function structureNestSegment( $record ) {
        if ( $record[ 'original_translation' ] == null ) {
            $original_translation = $record[ 'translation' ];
        } else {
            $original_translation = $record[ 'original_translation' ];
        }

        $this->current_segment = new ArrayObject( [
                'original_translation' => $original_translation,
                'translation'          => $record[ 'translation' ],
                'id'                   => $record[ 'segment_id' ],
                'source'               => $record[ 'segment_source' ],
                'status'               => $record[ 'translation_status' ],
            // TODO: the following `round` is wrong, this should be done later in a presentation layer...
                'edit_distance'        => round( $record[ 'edit_distance' ] / 1000, 2 ),
                'time_to_edit'         => round( $record[ 'time_to_edit' ] / 1000, 2 ),
                'issues'               => [],
                'qa_checks'            => []
        ] );

        array_push( $this->all_segments, $this->current_segment );

        array_push(
                $this->current_file[ 'segments' ],
                $this->current_segment
        );
    }

    private function structureNestIssue( $record ) {
        $this->current_issue = new ArrayObject( [
                'id'               => $record[ 'issue_id' ],
                'created_at'       => $this->filterDate( $record[ 'issue_create_date' ] ),
                'category'         => $record[ 'issue_category' ],
                'category_options' => $record[ 'category_options' ],
                'severity'         => $record[ 'issue_severity' ],

                'start_offset' => $record[ 'issue_start_offset' ],
                'end_offset'   => $record[ 'issue_end_offset' ],

                'target_text'   => $record[ 'target_text' ],
                'comment'       => $record[ 'issue_comment' ],
                'replies_count' => $record[ 'issue_replies_count' ],
                'comments'      => []
        ] );

        array_push(
                $this->current_segment[ 'issues' ],
                $this->current_issue
        );

    }

    private function structureNestQaChecks( $record ) {
        $qa_check = new ArrayObject( [
                'severity' => $record[ 'warning_severity' ],
                'scope'    => $record[ 'warning_scope' ],
                'data'     => $record[ 'warning_data' ]
        ] );

        array_push(
                $this->current_segment[ 'qa_checks' ],
                $qa_check
        );
    }

    private function structureNestComment( $record ) {
        $comment = new ArrayObject( [
                'comment'    => $record[ 'comment_comment' ],
                'created_at' => $this->filterDate( $record[ 'comment_create_date' ] ),
                'uid'        => $record[ 'comment_uid' ]
        ] );

        array_push(
                $this->current_issue[ 'comments' ],
                $comment
        );

    }

    private function structureNestFile( $record ) {
        $this->current_file = new \ArrayObject( [
                'id'       => $record[ 'file_id' ],
                'filename' => $record[ 'file_filename' ],
                'segments' => []
        ] );

        array_push(
                $this->quality_report_structure[ 'chunk' ][ 'files' ],
                $this->current_file
        );
    }

    private function filterDate( $date ) {
        $out = $date;
        if ( $this->date_format != null ) {
            $datetime = new \DateTime( $date );
            $out      = $datetime->format( $this->date_format );
        }

        return $out;
    }

}