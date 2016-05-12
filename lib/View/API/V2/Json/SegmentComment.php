<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 12/05/16
 * Time: 16:39
 */

namespace API\V2\Json;


class SegmentComment {
    /**
     * @var \Comments_BaseCommentStruct[]
     */
    private $data;

    public function __construct( $data ) {
        $this->data = $data;
    }

    public function render() {
        $out = array();
        foreach ( $this->data as $record ) {

            $row   = array(
                    'id'           => $record->id,
                    'id_job'       => $record->id_job,
                    'id_segment'   => $record->id_segment,
                    'created_at'   => $this->formatDate( $record->create_date ),
                    'email'        => $record->email,
                    'full_name'    => $record->full_name,
                    'uid'          => $record->uid,
                    'resolved_at' =>  $this->formatDate( $record->resolve_date ),
                    'source_page'  => $record->source_page,
                    'message_type' => $record->message_type,
                    'message'      => $record->message
            );
            $out[] = $row;
        }

        return $out;
    }

    private function formatDate( $date ) {
        if ( $date == null ) {
            return null ;
        }
        
        $datetime = new \DateTime( $date );
        return $datetime->format( 'c' );
    }
}