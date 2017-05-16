<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 27/04/17
 * Time: 18.52
 *
 */

namespace RemoteFiles;


use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class RemoteFileServiceNameStruct  extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id_project;
    public $service;

}