<?php

namespace API\V2\Json  ;

class SegmentVersion {

    private $data ;

    public function __construct( $data ) {
        $this->data = $data ;
    }

    public function render() {
        $out = array();
        foreach($this->data as $version) {
            $row = array(
                'id'              => $version->id,
                'segmentId'       => $version->id_segment,
                'jobId'           => $version->id_job,
                'translation'     => $version->translation,
                'version_number'  => $version->version_number,
                'propagated_from' => $version->propagated_from,
                'createdAt'       => $version->creation_date,
            );
            $out[] = $row ;
        }

        return $out;
    }

}
