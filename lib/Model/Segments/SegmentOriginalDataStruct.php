<?php

namespace Model\Segments;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class SegmentOriginalDataStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public ?int      $id          = null;
    public int       $id_segment;
    protected string $map;
    protected array  $decoded_map = [];
    

    public function setMap( array $map ): SegmentOriginalDataStruct {
        $this->decoded_map = $map;
        $this->map         = json_encode( $map );

        return $this;
    }

    public function getMap(): array {
        if ( empty( $this->decoded_map ) ) {
            $this->decoded_map = json_decode( $this->map, true ) ?: [];
        }

        return $this->decoded_map;
    }

}