<?php

class Routes {

    public static function analyze( $params, $options = array() ) {
        $params = \Utils::ensure_keys( $params,
                array( 'project_name', 'id_project', 'password' )
        );

        $host = self::httpHost( $options );

        return $host . "/analyze/" .
        $params[ 'project_name' ] . "/" .
        $params[ 'id_project' ] . "-" .
        $params[ 'password' ];
    }

    public static function httpHost( $params ) {
        $host = INIT::$HTTPHOST;

        if ( !empty( $params[ 'http_host' ] ) ) {
            $host = $params[ 'http_host' ];
        }

        if ( empty( $host ) ) {
            throw new Exception( 'HTTP_HOST is not set ' );
        }

        return $host;
    }

}