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
                'id'              => (int)$version->id,
                'id_segment'      => (int)$version->id_segment,
                'id_job'          => (int)$version->id_job,
                'translation'     => \CatUtils::rawxliff2view( $version->translation ),
                'version_number'  => (int)$version->version_number,
                'propagated_from' => (int)$version->propagated_from,
                'created_at'      => $version->creation_date,
            );
            $out[] = $row ;
        }

        return $out;
    }

}
