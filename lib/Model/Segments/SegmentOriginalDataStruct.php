<?php

namespace Model\Segments;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class SegmentOriginalDataStruct extends AbstractDaoSilentStruct implements IDaoStruct
{

    public ?int $id = null;
    public int $id_segment;
    protected string $map = '';
    /** @var array<string, string> */
    protected array $decoded_map = [];


    /** @param array<string, string> $map */
    public function setMap(array $map): SegmentOriginalDataStruct
    {
        $this->decoded_map = $map;
        $this->map = json_encode($map) ?: '{}';

        return $this;
    }

    /** @return array<string, string> */
    public function getMap(): array
    {
        if (empty($this->decoded_map)) {
            $this->decoded_map = json_decode($this->map, true) ?: [];
        }

        return $this->decoded_map;
    }

}
