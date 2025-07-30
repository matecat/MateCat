<?php

namespace ConnectedServices\Google;

use ConnectedServices\AbstractProvider;
use ConnectedServices\ConnectedServiceUserModel;
use Exception;
use Google_Client;
use Google_Service_Oauth2;
use INIT;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class GoogleProvider extends AbstractProvider {

    const PROVIDER_NAME = 'google';

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
     * @param string|null $redirectUrl
     *
     * @return Google_Client
     * @throws Exception
     */
    public static function getClient( ?string $redirectUrl = null ): Google_Client {

        $client = new Google_Client();

        $client->setApplicationName( INIT::$GOOGLE_OAUTH_CLIENT_APP_NAME );
        $client->setClientId( INIT::$GOOGLE_OAUTH_CLIENT_ID );
        $client->setClientSecret( INIT::$GOOGLE_OAUTH_CLIENT_SECRET );
        $client->setRedirectUri( $redirectUrl ?? INIT::$GOOGLE_OAUTH_REDIRECT_URL );
        $client->setScopes( static::$OAUTH_SCOPES );
        $client->setAccessType( "offline" );
        $client->setApprovalPrompt( 'force' );
        $client->setIncludeGrantedScopes( true );
        $client->setPrompt( "consent" );
        $client->setLogger( self::getLogger() );

        return $client;
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
     * @param string $csrfTokenState *
     *
     * @throws Exception
     */
    public function getAuthorizationUrl( string $csrfTokenState ): string {
        $googleClient = static::getClient( $this->redirectUrl );
        $googleClient->setState( $csrfTokenState );

        return $googleClient->createAuthUrl();
    }

    /**
     * @param string $code
     *
     * @return AccessToken
     * @throws Exception
     */
    public function getAccessTokenFromAuthCode( string $code ): AccessToken {
        $googleClient = static::getClient();

        return new AccessToken( $googleClient->fetchAccessTokenWithAuthCode( $code ) );
    }

    /**
     * @param AccessToken $token
     *
     * @return ConnectedServiceUserModel
     * @throws \Google\Service\Exception
     * @throws Exception
     */
    public function getResourceOwner( \League\OAuth2\Client\Token\AccessToken $token ): ConnectedServiceUserModel {

        $googleClient = self::getClient( $this->redirectUrl );
        $googleClient->setAccessType( "offline" );
        $googleClient->setAccessToken( $token->__toArray() ); // __toArray defined in ConnectedServices\Google\AccessToken

        $plus    = new Google_Service_Oauth2( $googleClient );
        $fetched = $plus->userinfo->get();

        $user            = new ConnectedServiceUserModel();
        $user->email     = $fetched->getEmail();
        $user->name      = $fetched->getName();
        $user->lastName  = $fetched->getFamilyName();
        $user->picture   = $fetched->getPicture();
        $user->authToken = $token;
        $user->provider  = self::PROVIDER_NAME;

        return $user;

    }

}
