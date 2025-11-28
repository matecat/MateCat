<?php

namespace Utils\Constants;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/05/14
 * Time: 17.30
 *
 */
class ProjectStatus
{

    const string STATUS_NOT_READY_FOR_ANALYSIS = 'NOT_READY_FOR_ANALYSIS';
    const string STATUS_NOT_TO_ANALYZE         = 'NOT_TO_ANALYZE';
    const string STATUS_EMPTY                  = 'EMPTY';
    const string STATUS_NEW                    = 'NEW';
    const string STATUS_BUSY                   = 'BUSY';
    const string STATUS_FAST_OK                = 'FAST_OK';
    const string STATUS_DONE                   = 'DONE';

    const string PROJECT_QUEUE_HASH = 'project_completed:%u';

    public static array $ALLOWED_STATUSES = [
            self::STATUS_DONE,
            self::STATUS_FAST_OK,
            self::STATUS_NEW,
            self::STATUS_BUSY,
            self::STATUS_EMPTY,
            self::STATUS_NOT_TO_ANALYZE,
            self::STATUS_NOT_READY_FOR_ANALYSIS,
    ];

    public static function isAllowedStatus($status): bool
    {
        return in_array(strtoupper($status), self::$ALLOWED_STATUSES);
    }

} 