<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 27/04/17
 * Time: 18.52
 *
 */

namespace Model\RemoteFiles;


use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

class RemoteFileServiceNameStruct extends AbstractDaoSilentStruct implements IDaoStruct
{

    public int $id_project;
    public string $service;

}