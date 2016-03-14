<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 2/11/16
 * Time: 8:51 PM
 */

namespace Features\ReviewImproved\Model;

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

    private $all_segments = array();

    private $date_format;


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

    /**
     * @param $format
     */
    public function setDateFormat( $format ) {
        $this->date_format = $format;
    }

    private function buildQualityReportStructure( $records ) {
        $this->quality_report_structure = array(
                'chunk'   => array(
                        'review' => array(
                                'percentage' => $this->getChunkReview()->getReviewedPercentage(),
                                'is_pass'    => !!$this->getChunkReview()->is_pass,
                                'score'      => $this->getChunkReview()->score
                        ),
                        'files'  => array()
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

    public function getAllSegments() {
        return $this->all_segments;
    }

    private function buildFilesSegmentsNestedTree( $records ) {
        $current_file_id    = null;
        $current_segment_id = null;
        $current_issue_id   = null;

        foreach ( $records as $record ) {

            \Log::doLog( $record );

            if ( $current_file_id != $record[ 'file_id' ] ) {
                $this->structureNestFile( $record );
            }

            if ( $current_segment_id != $record[ 'segment_id' ] ) {
                $this->structureNestSegment( $record );
            }

            if ( $current_issue_id != $record[ 'issue_id' ] ) {
                $this->structureNestIssue( $record );
            }

            if ( $record[ 'comment_id' ] != null ) {
                $this->structureNestComment( $record );
            }

            $current_file_id    = $record[ 'file_id' ];
            $current_segment_id = $record[ 'segment_id' ];
            $current_issue_id   = $record[ 'issue_id' ];
        }

        Log::doLog( $this->quality_report_structure );
    }

    private function structureNestSegment( $record ) {
        $this->current_segment = new ArrayObject( array(
                'original_translation' => $record[ 'original_translation' ],
                'translation'          => $record[ 'translation' ],
                'id'                   => $record[ 'segment_id' ],
                'source'               => $record[ 'segment_source' ],
                'status'               => $record[ 'translation_status' ],
                'edit_distance'        => round( $record[ 'edit_distance'] / 1000, 2),
                'issues'               => array()
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