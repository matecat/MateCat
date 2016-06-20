<?php

namespace API\V2\Json  ;

use LQA\EntryStruct;

class SegmentTranslationIssue {

    private $categories ;

    public function __construct( ) {
    }

    public function renderItem( EntryStruct $record ) {
        $row = array(
            'comment'             => $record->comment,
            'created_at'          => date('c', strtotime( $record->create_date) ),
            'id'                  => $record->id,
            'id_category'         => $record->id_category,
            'id_job'              => $record->id_job,
            'id_segment'          => $record->id_segment,
            'is_full_segment'     => $record->is_full_segment,
            'severity'            => $record->severity,
            'start_node'          => $record->start_node,
            'start_offset'        => $record->start_offset,
            'end_node'            => $record->end_node,
            'end_offset'          => $record->end_offset,
            'translation_version' => $record->translation_version,
            'target_text'         => $record->target_text,
            'penalty_points'      => $record->penalty_points,
            'rebutted_at'         => $this->getDateValue( $record->rebutted_at )
        );
        return $row;
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
