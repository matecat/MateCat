<?php


class Routes {

    /**
     * @param $id_job
     * @param $password
     * @param array $options
     * @return string
     */
    public static function downloadXliff($id_job, $password, $options = array() ) {
        $host = self::httpHost( $options );
        // TODO: pass in a filename here as last param?
        return "$host/SDLXLIFF/$id_job/$password/$id_job.zip";
    }


    public static function downloadOriginal($id_job, $password, $filename = null, $download_type = 'all', $options = array() ) {
        $host = self::httpHost( $options );

        $params = array(
            'id_job'        => $id_job,
            'password'      => $password,
            'download_type' => $download_type
        );

        if ( !empty($filename) ) {
            $params['filename'] = $filename  ;
        }

        return "$host/?action=downloadOriginal&" .  Utils::buildQueryString( $params );
    }


    public static function downloadTranslation($id_job, $password, $id_file, $filename = null, $download_type = 'all', $options = array() ) {
        $host = self::httpHost( $options );

        $params = array(
            'id_job'        => $id_job,
            'id_file'       => $id_file,
            'password'      => $password,
            'download_type' => $download_type
        );

        if ( !empty($filename) ) {
            $params['filename'] = $filename  ;
        }

        return "$host/?action=downloadFile&" .  Utils::buildQueryString( $params );
    }

    public static function revise( $project_name, $id_job, $password, $source, $target, $options = array() ) {
        $host = self::httpHost( $options );
        return "$host/revise/$project_name/$source-$target/$id_job-$password";
    }

    public static function translate( $project_name, $id_job, $password, $source, $target, $options = array() ) {
        $host = self::httpHost( $options );
        return "$host/translate/$project_name/$source-$target/$id_job-$password";
    }

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

    /**
     * @param $params
     *
     * @return string
     */
    public static function pluginsBase( $options ) {
        return self::httpHost( $options ) . '/plugins';
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