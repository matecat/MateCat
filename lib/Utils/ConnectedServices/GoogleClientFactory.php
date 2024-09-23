<?php

namespace ConnectedServices;

use ConnectedServices\Google\GoogleClientLogsFormatter;
use Exception;
use Google_Client;
use INIT;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Utils;

class GoogleClientFactory {


    /**
     * @var array
     */
    protected static array $OAUTH_SCOPES = [
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile',
            'https://www.googleapis.com/auth/drive.file',
            'https://www.googleapis.com/auth/drive.install',
            'profile'
    ];

    /**
     * @var string
     */
    protected static string $LOGGER_NAME = 'google_client_logger';

    /**
     * @param string $redirectUri
     *
     * @return Google_Client
     * @throws Exception
     */
    protected static function create( string $redirectUri ): Google_Client {

        $client = new Google_Client();

        $client->setApplicationName( INIT::$OAUTH_CLIENT_APP_NAME );
        $client->setClientId( INIT::$OAUTH_CLIENT_ID );
        $client->setClientSecret( INIT::$OAUTH_CLIENT_SECRET );
        $client->setRedirectUri( $redirectUri );
        $client->setScopes( static::$OAUTH_SCOPES );
        $client->setAccessType( "offline" );
        $client->setApprovalPrompt( 'force' );
        $client->setIncludeGrantedScopes( true );
        $client->setPrompt( "consent" );
        $client->setLogger( self::getLogger() );

        return $client;
    }

    /**
     * @return bool
     */
    protected static function isAWebPageCall(): bool {
        // this client is initialized from a web server request but not ajax calls
        return php_sapi_name() !== 'cli' && !isset( $_SERVER[ 'argv' ] ) && isset( $_SESSION ) && !isset( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] );
    }

    /**
     * @return Logger
     * @throws Exception
     */
    protected static function getLogger(): Logger {
        $log           = new Logger( self::$LOGGER_NAME );
        $streamHandler = new StreamHandler( self::logFilePath(), Logger::INFO );
        $streamHandler->setFormatter( new GoogleClientLogsFormatter() );
        $log->pushHandler( $streamHandler );

        return $log;
    }

    /**
     * @return string
     */
    protected static function logFilePath(): string {
        return INIT::$LOG_REPOSITORY . '/' . self::$LOGGER_NAME . '.log';
    }

    /**
     * [SECURITY]
     * This method is meant to get a valid Google client without a user to generate a valid authentication url without passing to the client a CSRF token.
     * Must be user only to generate a valid Google Oauth url and a valid login sequence. This is secure because
     *
     * @param string $handlerUrl
     *
     * @return Google_Client
     * @throws Exception
     */
    public static function getGoogleClient( string $handlerUrl ): Google_Client {
        return static::create( $handlerUrl );
    }

}
