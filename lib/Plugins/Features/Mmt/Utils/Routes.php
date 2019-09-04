<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 07/09/17
 * Time: 16.36
 *
 */

namespace Features\Mmt\Utils;

use Routes as DefaultRoutes;

class Routes {

    public static function staticSrc( $file, $options = [] ){
        $host = DefaultRoutes::appRoot();
        return $host . "lib/Plugins/Features/Mmt/static/build/$file";
    }

}