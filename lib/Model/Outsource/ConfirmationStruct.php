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

    const string VENDOR_NAME = '';
    const int    VENDOR_ID = -1;
    const string REVIEW_ORDER_LINK = '';

    public ?int $id = null;
    public ?int $id_job = null;
    public ?string $password = null;
    public string $vendor_name = self::VENDOR_NAME;
    public int $id_vendor = self::VENDOR_ID;
    public ?string $create_date = null;
    public ?string $delivery_date = null;
    public string $currency = 'EUR';
    public float $price = 0.00;
    public ?string $quote_pid = null;

}