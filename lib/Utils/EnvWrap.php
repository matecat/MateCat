<?php
class EnvWrap {

    public static $DEVELOPMENT = 'development' ;

    public static function link( $source, $dest ) {
        if ( INIT::$ENV == self::$DEVELOPMENT ) {
            return copy($source, $dest) ;
        }
        else {
            return link($source, $dest) ;
        }
    }
}
