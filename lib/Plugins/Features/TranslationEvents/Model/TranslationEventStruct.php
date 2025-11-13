<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 07/02/2018
 * Time: 17:24
 */

namespace Plugins\Features\TranslationEvents\Model;


use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class TranslationEventStruct extends AbstractDaoSilentStruct implements IDaoStruct
{

    public ?int   $id             = null;
    public int    $uid;
    public int    $id_segment;
    public int    $id_job;
    public int    $version_number = 0;
    public int    $source_page;
    public string $status;
    public int    $final_revision = 0;
    public string $create_date;
    public ?int   $time_to_edit   = null;

}