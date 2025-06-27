<?php

use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDaoStruct;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 23/02/15
 * Time: 14.54
 */
class TranslationsSplit_SplitStruct extends AbstractDaoObjectStruct implements IDaoStruct {

    public $id_segment;

    public $id_job;

    public $source_chunk_lengths;

    public $target_chunk_lengths;

    /**
     * An empty struct
     * @return TranslationsSplit_SplitStruct
     */
    public static function getStruct() {
        return new TranslationsSplit_SplitStruct();
    }

}
