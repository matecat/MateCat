<?php

namespace API\V2\Json  ;

class SegmentTranslationIssue {

    public function __construct(  ) {
    }

    public function renderItem( $record ) {
        $row = array(
            'comment'             => $record->comment,
            'created_at'          => date('c', strtotime( $record->create_date) ),
            'id'                  => $record->id,
            'id_category'         => $record->id_category,
            'id_job'              => $record->id_job,
            'id_segment'          => $record->id_segment,
            'is_full_segment'     => $record->is_full_segment,
            'severity'            => $record->severity,
            'start_position'      => $record->start_position,
            'stop_position'       => $record->stop_position,
            'translation_version' => $record->translation_version,
            'category'            => $record->category,
            'target_text'         => $record->target_text,
        );
        return $row;
    }

    public function renderArray( $array ) {
        $out = array();

        \Log::doLog( $array);
        foreach($array as $record) {
            $out[] = $this->renderItem( $record );
        }

        return $out;
    }

}
