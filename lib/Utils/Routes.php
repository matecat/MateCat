<?php


class Routes {

    /**
     * @param       $requestInfo
     * @param array $options
     *
     * @return string
     * @throws Exception
     */
    public static function inviteToTeamConfirm( $requestInfo, Array $options = [] ) {

        $host = self::httpHost( $options );

        $jwtHandler = new SimpleJWT( [
                'invited_by_uid' => $requestInfo[ 'invited_by_uid' ],
                'email'          => $requestInfo[ 'email' ],
                'team_id'        => $requestInfo[ 'team_id' ],
        ] );

        $jwtHandler->setTimeToLive( 60 * 60 * 24 * 3 ); //3 days

        return "$host/api/app/teams/members/invite/$jwtHandler";

    }

    /**
     * @param       $requestInfo
     * @param array $options
     *
     * @return string
     * @throws Exception
     */
    public static function sendToTranslatorConfirm( $requestInfo, Array $options = [] ) {

//        $host = self::httpHost( $options );
//        $jwtHandler = new SimpleJWT( [
//                'invited_by_uid' => $requestInfo[ 'invited_by_uid' ],
//                'email'          => $requestInfo[ 'email' ],
//                'projects_name'  => $requestInfo[ 'project_name' ],
//                'id_job'         => $requestInfo[ 'id_job' ],
//                'password'       => $requestInfo[ 'password' ],
//                'source'         => $requestInfo[ 'source' ],
//                'target'         => $requestInfo[ 'target' ],
//        ] );
//
//        $jwtHandler->setTimeToLive( 60 * 60 * 24 * 3 ); //3 days

        return self::translate(
                $requestInfo[ 'project_name' ],
                $requestInfo[ 'id_job' ],
                $requestInfo[ 'password' ],
                $requestInfo[ 'source' ],
                $requestInfo[ 'target' ],
                $options
        );

//        return "$host/api/app/jobs/translator/share/$jwtHandler";

    }

    /**
     * @param       $requestInfo
     * @param array $options
     *
     * @return string
     * @throws Exception
     */
    public static function sendToTranslatorUpdate( $requestInfo, Array $options = [] ) {

        return self::translate(
                $requestInfo[ 'project_name' ],
                $requestInfo[ 'id_job' ],
                $requestInfo[ 'password' ],
                $requestInfo[ 'source' ],
                $requestInfo[ 'target' ],
                $options
        );

    }

    /**
     * @param       $confirmation_token
     * @param array $options
     *
     * @return string
     * @throws Exception
     */
    public static function passwordReset( $confirmation_token, $options = [] ) {
        $host = self::httpHost( $options );

        return "$host/api/app/user/password_reset/$confirmation_token";
    }

    /**
     * @param       $confirmation_token
     * @param array $options
     *
     * @return string
     * @throws Exception
     */
    public static function signupConfirmation( $confirmation_token, $options = [] ) {
        $host = self::httpHost( $options );

        return "$host/api/app/user/confirm/$confirmation_token";
    }

    /**
     * @param       $id_job
     * @param       $password
     * @param array $options
     *
     * @return string
     * @throws Exception
     */
    public static function qualityReport( $id_job, $password, $options = [] ) {
        $host = self::httpHost( $options );

        return "$host/api/v2/jobs/{$id_job}/{$password}/quality-report";
    }

    /**
     * @param       $id_job
     * @param       $password
     * @param array $options
     *
     * @return string
     * @throws Exception
     */
    public static function downloadXliff( $id_job, $password, $options = [] ) {
        $host = self::httpHost( $options );

        // TODO: pass in a filename here as last param?
        return "$host/SDLXLIFF/$id_job/$password/$id_job.zip";
    }

    /**
     * @param        $id_job
     * @param        $password
     * @param null   $filename
     * @param string $download_type
     * @param array  $options
     *
     * @return string
     * @throws Exception
     */
    public static function downloadOriginal( $id_job, $password, $filename = null, $download_type = 'all', $options = [] ) {
        $host = self::httpHost( $options );

        $params = [
                'id_job'        => $id_job,
                'password'      => $password,
                'download_type' => $download_type
        ];

        if ( !empty( $filename ) ) {
            $params[ 'filename' ] = $filename;
        }

        return "$host/?action=downloadOriginal&" . http_build_query( $params, null, '&', PHP_QUERY_RFC3986 );
    }

    /**
     * @param        $id_job
     * @param        $password
     * @param        $id_file
     * @param null   $filename
     * @param string $download_type
     * @param array  $options
     *
     * @return string
     * @throws Exception
     */
    public static function downloadTranslation( $id_job, $password, $id_file, $filename = null, $download_type = 'all', $options = [] ) {
        $host = self::httpHost( $options );

        $params = [
                'id_job'        => $id_job,
                'id_file'       => $id_file,
                'password'      => $password,
                'download_type' => $download_type
        ];

        if ( !empty( $filename ) ) {
            $params[ 'filename' ] = $filename;
        }

        return "$host/?action=downloadFile&" . http_build_query( $params, null, '&', PHP_QUERY_RFC3986 );
    }

    /**
     * @param       $project_name
     * @param       $id_job
     * @param       $password
     * @param       $source
     * @param       $target
     * @param array $options
     *
     * @return string
     * @throws Exception
     */
    public static function revise( $project_name, $id_job, $password, $source, $target, $options = [] ) {
        $host = self::httpHost( $options );
        $revise = 'revise' ;

        if ( isset( $options['revision_number']) && $options['revision_number'] > 1 ) {
            $revise .= $options['revision_number'] ;
        }

        $url = "$host/$revise/$project_name/$source-$target/$id_job-$password" ;

        if ( isset( $options['id_segment']) ) {
            $url .= '#' . $options['id_segment'] ;
        }

        return $url ;
    }

    /**
     * @param       $project_name
     * @param       $id_job
     * @param       $password
     * @param       $source
     * @param       $target
     * @param array $options
     *
     * @return string
     * @throws Exception
     */
    public static function translate( $project_name, $id_job, $password, $source, $target, $options = [] ) {
        $host = self::httpHost( $options );

        return "$host/translate/$project_name/$source-$target/$id_job-$password";
    }

    /**
     * @param       $params
     * @param array $options
     *
     * @return string
     * @throws Exception
     */
    public static function analyze( $params, $options = [] ) {
        $params = Utils::ensure_keys( $params,
                [ 'project_name', 'id_project', 'password' ]
        );

        $host = self::httpHost( $options );

        $project_name = Utils::friendly_slug( $params[ 'project_name' ] );

        return $host . "/analyze/" . $project_name . "/" . $params[ 'id_project' ] . "-" . $params[ 'password' ];
    }

    /**
     * @return string
     * @throws Exception
     */
    public static function manage() {
        $host = self::httpHost( null );

        return "$host/manage";
    }

    /**
     * @param array $options
     *
     * @return string
     * @throws Exception
     */
    public static function appRoot( $options = [] ) {
        $query = isset( $options[ 'query' ] ) ? $options[ 'query' ] : null;

        $url = self::httpHost( $options ) . \INIT::$BASEURL;

        if ( $query ) {
            $url .= '?' . http_build_query( $query );
        }

        return $url;
    }

    /**
     * @param array $options
     *
     * @return string
     * @throws Exception
     */
    public static function pluginsBase( $options = [] ) {
        return self::httpHost( $options ) . '/plugins';
    }

    /**
     * @param $params
     *
     * @return mixed
     * @throws Exception
     */
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