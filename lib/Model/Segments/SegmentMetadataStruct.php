<?php

namespace Model\Segments;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class SegmentMetadataStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public ?int   $id_segment = null;
    public string $meta_key;
    public string $meta_value;
}