<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 2/11/16
 * Time: 8:51 PM
 */

namespace Features\ReviewImproved\Model;

use Features\ReviewImproved\ChunkReviewModel;
use Log,
        ArrayObject;
use LQA\ChunkReviewDao;


class QualityReportModel {

    /**
     * @var \Chunks_ChunkStruct
     */
    private $chunk;

    private $quality_report_structure = array();

    private $current_file = array();

    private $current_segment = array();

    private $current_issue = array();

    private $chunk_review;

    private $chunk_review_model;

    private $all_segments = array();

    private $date_format;

    private $avg_time_to_edit ;
    private $avg_edit_distance ;


    /**
     * @param \Chunks_ChunkStruct $chunk
     */
    public function __construct( \Chunks_ChunkStruct $chunk ) {
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
            $this->chunk_review = ChunkReviewDao::findOneChunkReviewByIdJobAndPassword(
                    $this->chunk->id, $this->chunk->password
            );
        }

        return $this->chunk_review;
    }

    public function getChunkReviewModel() {
        if ( $this->chunk_review_model == null ) {
            $this->chunk_review_model = new ChunkReviewModel( $this->getChunkReview() );
        }

        return $this->chunk_review_model;
    }

    /**
     * @param $format
     */
    public function setDateFormat( $format ) {
        $this->date_format = $format;
    }

    private function __setAverages() {
        $dao = new QualityReportDao() ;
        $avgs = $dao->getAverages( $this->getChunk() );

        $this->avg_edit_distance = round( $avgs['avg_edit_distance'] / 1000, 2);
        $this->avg_time_to_edit = round( $avgs['avg_time_to_edit'] / 1000, 2);
    }

    private function buildQualityReportStructure( $records ) {

        $this->__setAverages();

        $scoreFormatted = number_format( $this->getChunkReviewModel()->getScore(), 2, '.', ',' );

        $this->quality_report_structure = array(
                'chunk'   => array(
                        'review' => array(
                                'percentage'    => $this->getChunkReview()->getReviewedPercentage(),
                                'is_pass'       => !!$this->getChunkReview()->is_pass,
                                'score'         => $scoreFormatted,
                                'reviewer_name' => $this->getReviewerName()
                        ),
                        'files'  => array(),
                        'avg_time_to_edit' => $this->avg_time_to_edit,
                        'avg_edit_distance' => $this->avg_edit_distance
                ),
                'job'     => array(
                        'source' => $this->chunk->getJob()->source,
                        'target' => $this->chunk->getJob()->target,
                ),
                'project' => array(
                        'metadata'   => $this->getProject()->getMetadataAsKeyValue(),
                        'id'         => $this->getProject()->id,
                        'created_at' => $this->filterDate(
                                $this->getProject()->create_date
                        )
                )
        );


        $this->buildFilesSegmentsNestedTree( $records );

        return $this->quality_report_structure;

    }

    /**
     * @return string
     */
    private function getReviewerName() {
        $completion_event = \Chunks_ChunkCompletionEventDao::lastCompletionRecord(
                $this->chunk, array( 'is_review' => true )
        );
        $name             = '';

        if ( $completion_event[ 'uid' ] != null ) {
            $userDao = new \Users_UserDao( \Database::obtain() );
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

            if ( $current_issue_id != $record[ 'issue_id' ] && $record['issue_id'] !== null ) {
                $this->structureNestIssue( $record );
            }

            if ( $record[ 'comment_id' ] != null ) {
                $this->structureNestComment( $record );
            }

            if ( $record[ 'warning_scope' ] != null ) {
                $this->structureNestQaChecks( $record ) ;
            }

            $current_file_id    = $record[ 'file_id' ];
            $current_segment_id = $record[ 'segment_id' ];
            $current_issue_id   = $record[ 'issue_id' ];
        }

    }

    private function structureNestSegment( $record ) {
        if ( $record['original_translation'] == null ) {
            $original_translation = $record['translation'];
        }
        else {
            $original_translation = $record['original_translation'];
        }

        $this->current_segment = new ArrayObject( array(
                'original_translation' => $original_translation,
                'translation'          => $record[ 'translation' ],
                'id'                   => $record[ 'segment_id' ],
                'source'               => $record[ 'segment_source' ],
                'status'               => $record[ 'translation_status' ],
                // TODO: the following `round` is wrong, this should be done later in a presentation layer...
                'edit_distance'        => round( $record[ 'edit_distance' ] / 1000, 2 ),
                'time_to_edit'         => round( $record[ 'time_to_edit' ] / 1000, 2 ),
                'issues'               => array(),
                'qa_checks'            => array()
        ) );

        array_push( $this->all_segments, $this->current_segment );

        array_push(
                $this->current_file[ 'segments' ],
                $this->current_segment
        );
    }

    private function structureNestIssue( $record ) {
        $this->current_issue = new \ArrayObject( array(
                'id'            => $record[ 'issue_id' ],
                'created_at'    => $this->filterDate( $record[ 'issue_create_date' ] ),
                'category'      => $record[ 'issue_category' ],
                'severity'      => $record[ 'issue_severity' ],
                'target_text'   => $record[ 'target_text' ],
                'comment'       => $record[ 'issue_comment' ],
                'replies_count' => $record[ 'issue_replies_count' ],
                'comments'      => array()
        ) );

        array_push(
                $this->current_segment[ 'issues' ],
                $this->current_issue
        );

    }

    private function structureNestQaChecks( $record ) {
        $qa_check = new ArrayObject( array(
                'severity'    => $record[ 'warning_severity' ],
                'scope'       => $record[ 'warning_scope' ] ,
                'data'        => $record[ 'warning_data' ]
        ) );

        array_push(
                $this->current_segment[ 'qa_checks' ],
                $qa_check
        );
    }
    private function structureNestComment( $record ) {
        $comment = new ArrayObject( array(
                'comment'    => $record[ 'comment_comment' ],
                'created_at' => $this->filterDate( $record[ 'comment_create_date' ] ),
                'uid'        => $record[ 'comment_uid' ]
        ) );

        array_push(
                $this->current_issue[ 'comments' ],
                $comment
        );

    }

    private function structureNestFile( $record ) {
        $this->current_file = new \ArrayObject( array(
                'id'       => $record[ 'file_id' ],
                'filename' => $record[ 'file_filename' ],
                'segments' => array()
        ) );

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