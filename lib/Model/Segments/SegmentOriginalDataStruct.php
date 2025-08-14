<?php

namespace Model\Segments;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class SegmentOriginalDataStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public ?int  $id = null;
    public int   $id_segment;
    public array $map;
}