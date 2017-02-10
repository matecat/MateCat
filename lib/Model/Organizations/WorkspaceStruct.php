<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 08/02/17
 * Time: 18.34
 *
 */

namespace Organizations;


use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

class WorkspaceStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    public $id;
    public $name;
    public $id_organization;

    /**
     * @var WorkspaceOptionsStruct
     */
    public $options;

}