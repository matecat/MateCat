<?php

namespace API\V2\Json  ;

class SegmentTranslationIssue {

    private $data ;

    public function __construct( $data ) {
        if ( (!empty($data)) && \Utils::is_assoc( $data ) ) {
            $this->data = array( $data );
        }
        else {
            $this->data = $data ;
        }
    }

    public function render() {
        $out = array();

        \Log::doLog( $this->data );

        foreach($this->data as $record) {
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
            );

            $out[] = $row ;
        }

        return $out;
    }

}
