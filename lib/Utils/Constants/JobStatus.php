<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 21/08/14
 * Time: 19.08
 *
 */

class Constants_JobStatus {

    /**
     * Created by PhpStorm.
     * @author domenico domenico@translated.net / ostico@gmail.com
     * Date: 12/05/14
     * Time: 17.30
     *
     */

    const STATUS_ACTIVE    = 'active';
    const STATUS_ARCHIVED  = 'archived';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_DELETED   = 'deleted';

    public static $ALLOWED_STATUSES = [
            self::STATUS_ACTIVE,
            self::STATUS_ARCHIVED,
            self::STATUS_CANCELLED
    ];

    public static function isAllowedStatus( $status ) {
        return in_array( strtolower( $status ), self::$ALLOWED_STATUSES );
    }

} 