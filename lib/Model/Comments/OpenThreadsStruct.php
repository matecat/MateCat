<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 27/04/17
 * Time: 14.50
 *
 */

namespace Model\Comments;


use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class OpenThreadsStruct extends AbstractDaoSilentStruct implements IDaoStruct
{

    public int    $id_project;
    public string $password;
    public int    $id_job;
    public int    $count;

}