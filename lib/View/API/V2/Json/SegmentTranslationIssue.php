<?php

namespace API\V2\Json  ;

use LQA\EntryStruct;
use LQA\EntryCommentDao;

class SegmentTranslationIssue {

    private $categories ;

    public function __construct( ) {
    }

    public function renderItem( \DataAccess_AbstractDaoSilentStruct $record ) {

        $dao = new EntryCommentDao();

        $comments = $dao->findByIssueId( $record->id );

        $record = new EntryStruct( $record->getArrayCopy() );

        $row = [
                'comment'             => $record->comment,
                'created_at'          => date( 'c', strtotime( $record->create_date ) ),
                'id'                  => (int)$record->id,
                'id_category'         => (int)$record->id_category,
                'id_job'              => (int)$record->id_job,
                'id_segment'          => (int)$record->id_segment,
                'is_full_segment'     => $record->is_full_segment,
                'severity'            => $record->severity,
                'start_node'          => $record->start_node,
                'start_offset'        => $record->start_offset,
                'end_node'            => $record->end_node,
                'end_offset'          => $record->end_offset,
                'translation_version' => $record->translation_version,
                'target_text'         => $record->target_text,
                'penalty_points'      => $record->penalty_points,
                'rebutted_at'         => $this->getDateValue( $record->rebutted_at ),
                'diff'                => $record->getDiff(),
                'comments'            => $comments
        ];

        return $row;
    }

    public function genCSVTmpFile( $data ) {
        $filePath   = tempnam( "/tmp", "SegmentsIssuesComments_" );
        $csvHandler = new \SplFileObject( $filePath, "w" );
        $csvHandler->setCsvControl( ';' );

        $csv_fields = [
                "ID Segment",
                "Category",
                "Severity",
                "Selected Text",
                "Message",
                "Created At",
        ];

        $csvHandler->fputcsv( $csv_fields );

        foreach ( $data as $record ) {

            $dao = new EntryCommentDao();

            $comments = $dao->findByIssueId( $record->id );
            foreach ( $comments as $c ) {

                $combined = array_combine( $csv_fields, array_fill( 0, count( $csv_fields ), '' ) );

                $combined[ "ID Segment" ]    = $record->id_segment;
                $combined[ "Category" ]      = $record->category_label;
                $combined[ "Severity" ]      = $record->severity;
                $combined[ "Selected Text" ] = $record->target_text;
                $combined[ "Message" ]       = $c->comment;
                $combined[ "Created At" ]    = $this->getDateValue( $c->create_date );
                $csvHandler->fputcsv( $combined );
            }

        }

        return $filePath;
    }

    private function decodeCategoryName( $id ) {

        return null;
    }

    public function renderArray( $array ) {
        $out = array();

        foreach($array as $record) {
            $out[] = $this->renderItem( $record );
        }

        return $out;
    }

    private function getDateValue( $strDate ) {
        if( $strDate != null ) {
            return date( 'c', strtotime( $strDate ) );
        }

        return null;
    }

}
