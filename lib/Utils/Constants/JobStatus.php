<?php

namespace Utils\Constants;
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 21/08/14
 * Time: 19.08
 *
 */
class JobStatus {

    /**
     * Created by PhpStorm.
     * @author domenico domenico@translated.net / ostico@gmail.com
     * Date: 12/05/14
     * Time: 17.30
     *
     */

    const string STATUS_ACTIVE    = 'active';
    const string STATUS_ARCHIVED  = 'archived';
    const string STATUS_CANCELLED = 'cancelled';
    const string STATUS_DELETED   = 'deleted';

    public static array $ALLOWED_STATUSES = [
            self::STATUS_ACTIVE,
            self::STATUS_ARCHIVED,
            self::STATUS_CANCELLED,
            self::STATUS_DELETED
    ];

    public static function isAllowedStatus( string $status ): bool {
        return in_array( strtolower( $status ), self::$ALLOWED_STATUSES );
    }

} 