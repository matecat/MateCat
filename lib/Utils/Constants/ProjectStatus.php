<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 12/05/14
 * Time: 17.30
 * 
 */
class Constants_ProjectStatus {

    const STATUS_NOT_READY_FOR_ANALYSIS = 'NOT_READY_FOR_ANALYSIS';
    const STATUS_NOT_TO_ANALYZE         = 'NOT_TO_ANALYZE';
    const STATUS_EMPTY                  = 'EMPTY';
    const STATUS_NEW                    = 'NEW';
    const STATUS_BUSY                   = 'BUSY';
    const STATUS_FAST_OK                = 'FAST_OK';
    const STATUS_DONE                   = 'DONE';

    const PROJECT_QUEUE_HASH            = 'project_completed:%u';

    public static $ALLOWED_STATUSES = [
            self::STATUS_DONE,
            self::STATUS_FAST_OK,
            self::STATUS_NEW,
            self::STATUS_BUSY,
            self::STATUS_EMPTY,
            self::STATUS_NOT_TO_ANALYZE,
            self::STATUS_NOT_READY_FOR_ANALYSIS,
    ];

    public static function isAllowedStatus( $status ){
        return in_array( strtoupper( $status ), self::$ALLOWED_STATUSES );
    }

} 