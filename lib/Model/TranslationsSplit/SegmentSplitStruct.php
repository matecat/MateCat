<?php

namespace Model\TranslationsSplit;

use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDaoStruct;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 23/02/15
 * Time: 14.54
 */
class SegmentSplitStruct extends AbstractDaoObjectStruct implements IDaoStruct
{

    /**
     * Primary key (Part 1)
     * @var int
     */
    public int $id_segment;

    /**
     * Primary key (Part 2)
     *
     * @var int
     */
    public int $id_job;

    /**
     * @var array|string
     */
    public $source_chunk_lengths;
    /**
     * @var array|string
     */
    public $target_chunk_lengths;

    /**
     * An empty struct
     * @return SegmentSplitStruct
     */
    public static function getStruct(): SegmentSplitStruct
    {
        return new SegmentSplitStruct();
    }

}
