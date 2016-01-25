<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/05/14
 * Time: 14.32
 * 
 */

class Constants_TranslationStatus {

    const STATUS_NEW        = 'NEW';
    const STATUS_DRAFT      = 'DRAFT';
    const STATUS_TRANSLATED = 'TRANSLATED';
    const STATUS_APPROVED   = 'APPROVED';
    const STATUS_REJECTED   = 'REJECTED';

    public static $REVIEWED_STATUSES = array(
        self::STATUS_APPROVED, self::STATUS_REJECTED
    );


}
