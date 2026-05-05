<?php

namespace Model\Segments;

use ArrayAccess;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\ArrayAccessTrait;
use Model\DataAccess\IDaoStruct;

class SegmentStruct extends AbstractDaoSilentStruct implements IDaoStruct, ArrayAccess
{

    use ArrayAccessTrait;

    public int $id;
    public int $id_file;
    public ?int $id_file_part = null;
    protected ?int $id_project = null; //keep private, this is not implemented in Database schema
    public string $internal_id;
    public ?string $xliff_mrk_id = null;
    public ?string $xliff_ext_prec_tags = null;
    public ?string $xliff_mrk_ext_prec_tags = null;
    public string $segment;
    public string $segment_hash;
    public ?string $xliff_mrk_ext_succ_tags = null;
    public ?string $xliff_ext_succ_tags = null;
    public int $raw_word_count;
    public bool $show_in_cattool = true;

}
