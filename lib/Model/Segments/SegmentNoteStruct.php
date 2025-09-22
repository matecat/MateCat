<?php

namespace Model\Segments;

use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class SegmentNoteStruct extends AbstractDaoSilentStruct implements IDaoStruct {

    public ?int    $id          = null;
    public int     $id_segment;
    public ?string $internal_id = null;
    public ?string $note        = null;
    public ?string $json        = null;

}
