<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 27/04/17
 * Time: 17.53
 *
 */

namespace Model\Jobs;


use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class WarningsCountStruct extends AbstractDaoSilentStruct implements IDaoStruct
{

    public int $count;
    public int $id_job;
    public string $password;
    public string $segment_list;

}