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

    /** @var array<string, int> */
    public static array $DB_STATUSES_MAP = [
        self::STATUS_NEW => 1,
        self::STATUS_DRAFT => 2,
        self::STATUS_TRANSLATED => 3,
        self::STATUS_APPROVED => 4,
        self::STATUS_REJECTED => 5,
        self::STATUS_FIXED => 6,
        self::STATUS_APPROVED2 => 8,
    ];

    /** @var list<string> */
    public static array $STATUSES = [
        self::STATUS_NEW,
        self::STATUS_DRAFT,
        self::STATUS_TRANSLATED,
        self::STATUS_APPROVED,
        self::STATUS_APPROVED2,
    ];

    /** @var list<string> */
    public static array $INITIAL_STATUSES = [
        self::STATUS_NEW,
        self::STATUS_DRAFT
    ];

    /** @var list<string> */
    public static array $TRANSLATION_STATUSES = [
        self::STATUS_TRANSLATED
    ];


    /** @var list<string> */
    public static array $REVISION_STATUSES = [
        self::STATUS_APPROVED,
        self::STATUS_APPROVED2,
        self::STATUS_REJECTED
    ];

    /**
     * Statuses a pre-confirmed 100% or 101% match can be stored with,
     * accepted by the pretranslate_101_status and pretranslate_100_status options.
     *
     * @var list<string>
     */
    private const array PRE_TRANSLATE_STATUSES = [
        self::STATUS_TRANSLATED,
        self::STATUS_APPROVED,
        self::STATUS_APPROVED2,
    ];

    /**
     * Maps a pre-confirm status option, in any letter case, to its constant.
     *
     * @return string|null the constant, or null when the value is not a pre-confirm status
     */
    public static function preTranslateStatus(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $status = strtoupper($value);

        return in_array($status, self::PRE_TRANSLATE_STATUSES, true) ? $status : null;
    }

    public static function isReviewedStatus(string $status): bool
    {
        return in_array($status, TranslationStatus::$REVISION_STATUSES);
    }

    public static function isNotInitialStatus(string $status): bool
    {
        return !in_array($status, TranslationStatus::$INITIAL_STATUSES);
    }

}
