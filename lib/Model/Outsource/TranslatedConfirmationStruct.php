<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/03/17
 * Time: 17.13
 *
 */

namespace Outsource;

use DataAccess\IDaoStruct;

class TranslatedConfirmationStruct extends ConfirmationStruct implements IDaoStruct {

    const VENDOR_NAME = 'Translated';
    const VENDOR_ID   = 1; //Hardcoded, this value would be stored in a database table to avoid implementation collisions, but for now there are no other providers

    const REVIEW_ORDER_LINK = "https://www.translated.net/int/ots.php?pid=";

}