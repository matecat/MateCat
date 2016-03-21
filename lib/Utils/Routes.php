<?php

class Routes {

    public static function analyze( $params ) {
        $params = Utils::ensure_keys($params,
                array('project_name', 'id_project', 'password')
        );

        return INIT::$HTTPHOST . "/analyze/" .
            $params[ 'project_name' ] . "/" .
            $params[ 'id_project' ] . "-" .
            $params[ 'password' ];
    }

    public static function translate( $params ) {

    }

    public static function manage( $params ) {

    }

    public static function revise( $params ) {

    }

}