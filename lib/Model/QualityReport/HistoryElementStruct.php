<?php

namespace Model\QualityReport;

use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDaoStruct;

/**
 * @property int $id_segment
 * @property string $translation
 * @property int $version_number
 * @property int $source_page
 */
class HistoryElementStruct extends AbstractDaoObjectStruct implements IDaoStruct
{
    public int $id_segment;
    public string $translation;
    public int $version_number;
    public ?int $source_page = null;
    public ?string $status = null;
    public ?string $create_date = null;
    public ?string $creation_date = null;
}
