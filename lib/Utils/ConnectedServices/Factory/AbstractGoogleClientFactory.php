<?php

namespace ConnectedServices\Factory;

use ConnectedServices\Google\GoogleClientLogsFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

abstract class AbstractGoogleClientFactory {

    /**
     * @var array
     */
    private static $OAUTH_SCOPES = [
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/userinfo.profile',
            'https://www.googleapis.com/auth/drive',
            'https://www.googleapis.com/auth/drive.install',
            'profile'
    ];

    /**
     * @var string
     */
    private static $LOGGER_NAME = 'google_client_logger';

    /**
     * @param string $redirectUri
     *
     * @return \Google_Client
     * @throws \Exception
     */
    public static function create( $redirectUri ) {

        $client = new \Google_Client();

        $client->setApplicationName( \INIT::$OAUTH_CLIENT_APP_NAME );
        $client->setClientId( \INIT::$OAUTH_CLIENT_ID );
        $client->setClientSecret( \INIT::$OAUTH_CLIENT_SECRET );
        $client->setRedirectUri( $redirectUri );
        $client->setScopes( static::$OAUTH_SCOPES );
        $client->setAccessType( "offline" );
        $client->setApprovalPrompt('force');
        $client->setIncludeGrantedScopes(true);
        $client->setPrompt( "consent" );
        $client->setLogger(self::getLogger());

        return $client;
    }

    /**
     * @return Logger
     * @throws \Exception
     */
    private static function getLogger() {
        $log = new Logger( self::$LOGGER_NAME );
        $streamHandler = new StreamHandler( self::logFilePath(),  Logger::INFO );
        $streamHandler->setFormatter( new GoogleClientLogsFormatter() );
        $log->pushHandler( $streamHandler );

        return $log;
    }

    /**
     * @return string
     */
    private static function logFilePath() {
        return \INIT::$LOG_REPOSITORY . '/' . self::$LOGGER_NAME . '.log';
    }
}