<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 04/01/23
 * Time: 12:22
 *
 */

namespace Files;

use FilesStorage\AbstractFilesStorage;

class File {

    /**
     * @param $filepath
     */
    public static function create( $filepath ) {
        if ( !self::exists( $filepath ) ) {
            touch( $filepath );
        }
    }

    /**
     * @param $filepath
     */
    public static function delete( $filepath ) {
        if ( self::exists( $filepath ) ) {
            unlink( $filepath );
        }
    }

    /**
     * @param $resource
     *
     * @return bool
     */
    public static function exists( $resource ) {
        return file_exists( $resource );
    }

    /**
     * @param $filepath
     *
     * @return string|string[]
     */
    public static function info( $filepath, $options = null ) {
        return AbstractFilesStorage::pathinfo_fix( $filepath, $options );
    }
}