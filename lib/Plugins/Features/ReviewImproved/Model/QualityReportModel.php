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


class QualityReportModel {


    private $segmentsInfo;

    /**
     * @var \Chunks_ChunkStruct
     */
    private $chunk;

    private $quality_report_structure = array();

    private $current_file = array();

    private $current_segment = array();

    private $current_issue = array();

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

    public function getSegmentsStructure() {
        $records = QualityReportDao::getSegmentsForQualityReport( $this->chunk );

        return $this->buildQualityReportStructure( $records );
    }

    private function buildQualityReportStructure( $records ) {

        $this->quality_report_structure = array();


        /**
         * First thing to do here is extract the file info.
         * File is the root entity of our structure.
         *
         */

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

            if ( $record['comment_id'] != null ) {
                $this->structureNestComment( $record );
            }

            $current_file_id    = $record[ 'file_id' ];
            $current_segment_id = $record[ 'segment_id' ];
            $current_issue_id   = $record[ 'issue_id' ];
        }

        Log::doLog( $this->quality_report_structure );

        return $this->quality_report_structure;

    }

    private function structureNestSegment( $record ) {
        $this->current_segment = new ArrayObject( array(
                'original_translation' => $record[ 'original_translation' ],
                'id_segment'           => $record[ 'segment_id' ],
                'issues'               => array()
        ) );

        array_push(
                $this->current_file[ 'segments' ],
                $this->current_segment
        );
    }

    private function structureNestIssue( $record ) {
        $this->current_issue = new \ArrayObject( array(
                'id'          => $record[ 'issue_id' ],
                'create_date' => $record[ 'issue_create_date' ],
                'category'    => $record[ 'issue_category' ],
                'comments'    => array()
        ) );

        array_push(
                $this->current_segment[ 'issues' ],
                $this->current_issue
        );

    }

    private function structureNestComment( $record ) {
        $comment = new ArrayObject(array(
                'comment'     => $record[ 'comment_comment' ],
                'create_date' => $record[ 'comment_create_date' ],
                'uid'         => $record[ 'comment_uid' ]
        ));

        array_push(
                $this->current_issue['comments'],
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
                $this->quality_report_structure,
                $this->current_file
        );
    }
}