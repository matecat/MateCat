<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 17/07/25
 * Time: 17:25
 *
 */

namespace Controller\Abstracts\Authentication;

use Exception;

trait SessionStarter {

    /**
     * @throws Exception
     */
    protected static function sessionStart() {
        $session_status = session_status();
        if ( $session_status == PHP_SESSION_NONE ) {
            session_start();
        } elseif ( $session_status == PHP_SESSION_DISABLED ) {
            throw new Exception( "MateCat needs to have sessions. Sessions must be enabled." );
        }
    }

}