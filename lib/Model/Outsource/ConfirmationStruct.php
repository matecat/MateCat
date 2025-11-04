<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/03/17
 * Time: 17.13
 *
 */

namespace Model\Outsource;


use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;

abstract class ConfirmationStruct extends AbstractDaoSilentStruct implements IDaoStruct
{

    const string VENDOR_NAME       = '';
    const int    VENDOR_ID         = -1;
    const string REVIEW_ORDER_LINK = '';

    public $id;
    public $id_job;
    public $password;
    public $vendor_name = self::VENDOR_NAME;
    public $id_vendor   = self::VENDOR_ID;
    public $create_date;
    public $delivery_date;
    public $currency    = 'EUR';
    public $price;
    public $quote_pid;

}