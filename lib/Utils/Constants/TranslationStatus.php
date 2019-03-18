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
    const STATUS_FIXED      = 'FIXED';
    const STATUS_REBUTTED   = 'REBUTTED';

    public static $DB_STATUSES_MAP = [
            self::STATUS_NEW        => 1,
            self::STATUS_DRAFT      => 2,
            self::STATUS_TRANSLATED => 3,
            self::STATUS_APPROVED   => 4,
            self::STATUS_REJECTED   => 5,
            self::STATUS_FIXED      => 6,
            self::STATUS_REBUTTED   => 7
    ] ;

    public static $STATUSES = array(
            self::STATUS_NEW,
            self::STATUS_DRAFT,
            self::STATUS_TRANSLATED,
            self::STATUS_APPROVED,
            self::STATUS_REBUTTED,
    );

    public static $INITIAL_STATUSES = array(
            self::STATUS_NEW,
            self::STATUS_DRAFT
    );

    public static $TRANSLATION_STATUSES = array(
            self::STATUS_TRANSLATED
    );


    public static $REVISION_STATUSES = array(
            self::STATUS_APPROVED,
            self::STATUS_REJECTED
    );

    public static $POST_REVISION_STATUSES = array(
            self::STATUS_FIXED,
            self::STATUS_REBUTTED
    );

    public static function isReviewedStatus( $status ) {
        return in_array( $status, Constants_TranslationStatus::$REVISION_STATUSES );
    }
}
