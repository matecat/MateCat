<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 26/07/24
 * Time: 11:59
 *
 */

namespace QualityReport;

use DataAccess\AbstractDaoObjectStruct;
use DataAccess\IDaoStruct;

/**
 * @property int    $id_segment
 * @property string $translation
 * @property int    $version_number
 * @property int    $source_page
 */
class SegmentEventsStruct extends AbstractDaoObjectStruct implements IDaoStruct {

    /**
     * @var int
     */
    protected int $id_segment;
    /**
     * @var string
     */
    protected string $translation;
    /**
     * @var int
     */
    protected int $version_number;
    /**
     * @var int
     */
    protected int $source_page;

}