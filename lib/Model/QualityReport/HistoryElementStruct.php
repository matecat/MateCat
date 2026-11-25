<?php

namespace Model\QualityReport;

use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDaoStruct;

/**
 * One row of TranslationVersionDao::historyEvents(): a translation event, or the version-0 row,
 * which has no event and so carries a null id, source_page, status and create_date.
 *
 * @property int $id_segment
 * @property string $translation
 * @property int $version_number
 * @property int $source_page
 */
class HistoryElementStruct extends AbstractDaoObjectStruct implements IDaoStruct
{
    /** The segment_translation_events id; null on the version-0 row. */
    public ?int $id = null;
    public int $id_segment;
    public string $translation;
    public int $version_number;
    public ?int $source_page = null;
    public ?string $status = null;
    /** When the event happened. */
    public ?string $create_date = null;
    /** Only set on the version-0 row: when that text was replaced by the first save. */
    public ?string $creation_date = null;
}
