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

    /**
     * @var \SplFileObject
     */
    private $csvHandler;

    public function __construct( $data ) {
        $this->data = $data;
    }

    public function render() {
        $out = array();
        foreach ( $this->data as $record ) {

            $row   = array(
                    'id'           => (int)$record->id,
                    'id_job'       => (int)$record->id_job,
                    'id_segment'   => $record->id_segment,
                    'created_at'   => $this->formatDate( $record->create_date ),
                    'email'        => $record->email,
                    'full_name'    => $record->full_name,
                    'uid'          => (int)$record->uid,
                    'resolved_at' =>  $this->formatDate( $record->resolve_date ),
                    'source_page'  => $record->source_page,
                    'message_type' => $record->message_type,
                    'message'      => \Comments_CommentDao::placeholdContent($record->message)
            );
            $out[] = $row;
        }

        return $out;
    }

    public function cleanDownloadResource(){

        $path = $this->csvHandler->getRealPath();
        unset( $this->csvHandler );
        @unlink( $path );

    }

    public function genCSVTmpFile(){
        $filePath = tempnam("/tmp", "SegmentsComments_");
        $csvHandler = new \SplFileObject($filePath, "w");
        $csvHandler->setCsvControl( ';' );

        $this->csvHandler = $csvHandler; // set the handler to allow to clean resource

        $csv_fields = [
                "ID Segment",
                "Email",
                "Full Name",
                "Message",
                "Created At",
                "Resolved",
                "Resolved At"
        ];

        $csvHandler->fputcsv( $csv_fields );

        foreach ( $this->data as $d ) {

            $combined = array_combine( $csv_fields, array_fill( 0, count( $csv_fields ), '' ) );

            $combined[ "ID Segment" ]  = $d->id_segment;
            $combined[ "Email" ]       = $d->email;
            $combined[ "Full Name" ]   = $d->full_name;
            $combined[ "Message" ]     = $d->message;
            $combined[ "Created At" ]  = $this->formatDate( $d->create_date );
            $combined[ "Resolved" ]    = ( !empty( $d->resolve_date ) ) ? "Yes" : "No";
            $combined[ "Resolved At" ] = ( !empty( $d->resolve_date ) ) ? $this->formatDate( $d->resolve_date ) : "N/A";

            $csvHandler->fputcsv( $combined );

        }


        return $filePath;
    }

    private function formatDate( $date ) {
        if ( $date == null ) {
            return null ;
        }
        
        $datetime = new \DateTime( $date );
        return $datetime->format( 'c' );
    }
}