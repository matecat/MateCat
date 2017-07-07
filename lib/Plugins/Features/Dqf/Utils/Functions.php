<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 06/07/2017
 * Time: 16:53
 */

namespace Features\Dqf\Utils;

use INIT;

class Functions {

    public static function scopeId( $id ) {
        return INIT::$DQF_ID_PREFIX . '-' . $id ;
    }

}