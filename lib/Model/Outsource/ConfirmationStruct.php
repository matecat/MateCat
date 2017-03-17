<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/03/17
 * Time: 17.13
 *
 */

namespace Outsource;


use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;

abstract class ConfirmationStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct {

    const VENDOR_NAME = null;
    const VENDOR_ID = null;

    public $id;
    public $id_job;
    public $password;
    public $vendor_name = self::VENDOR_NAME;
    public $id_vendor   = self::VENDOR_ID;
    public $create_date;
    public $delivery_date;

}