<?php

namespace Utils\Constants;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/05/14
 * Time: 14.32
 *
 */
class TranslationStatus
{

    const string STATUS_NEW = 'NEW';
    const string STATUS_DRAFT = 'DRAFT';
    const string STATUS_TRANSLATED = 'TRANSLATED';
    const string STATUS_APPROVED = 'APPROVED';
    const string STATUS_APPROVED2 = 'APPROVED2';
    const string STATUS_REJECTED = 'REJECTED';
    const string STATUS_FIXED = 'FIXED';

    public static array $DB_STATUSES_MAP = [
        self::STATUS_NEW => 1,
        self::STATUS_DRAFT => 2,
        self::STATUS_TRANSLATED => 3,
        self::STATUS_APPROVED => 4,
        self::STATUS_REJECTED => 5,
        self::STATUS_FIXED => 6,
        self::STATUS_APPROVED2 => 8,
    ];

    public static array $STATUSES = [
        self::STATUS_NEW,
        self::STATUS_DRAFT,
        self::STATUS_TRANSLATED,
        self::STATUS_APPROVED,
        self::STATUS_APPROVED2,
    ];

    public static array $INITIAL_STATUSES = [
        self::STATUS_NEW,
        self::STATUS_DRAFT
    ];

    public static array $TRANSLATION_STATUSES = [
        self::STATUS_TRANSLATED
    ];


    public static array $REVISION_STATUSES = [
        self::STATUS_APPROVED,
        self::STATUS_APPROVED2,
        self::STATUS_REJECTED
    ];

    public static function isReviewedStatus($status): bool
    {
        return in_array($status, TranslationStatus::$REVISION_STATUSES);
    }

    public static function isNotInitialStatus($status): bool
    {
        return !in_array($status, TranslationStatus::$INITIAL_STATUSES);
    }

}
