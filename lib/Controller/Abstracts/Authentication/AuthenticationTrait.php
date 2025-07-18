<?php

namespace Controller\Abstracts\Authentication;

use Exception;
use Model\ApiKeys\ApiKeyStruct;
use Model\Users\UserStruct;
use ReflectionException;
use Stomp\Transport\Message;
use Utils\ActiveMQ\AMQHandler;
use Utils\Registry\AppConfig;

/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 19/09/24
 * Time: 15:00
 *
 */
trait AuthenticationTrait {

    use SessionStarter;

    protected bool       $userIsLogged;
    protected UserStruct $user;

    /**
     * @var ?string
     */
    protected ?string $api_key = null;
    /**
     * @var ?string
     */
    protected ?string $api_secret = null;

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function identifyUser( ?bool $useSession = true ) {

        $_session = [];
        if ( $useSession ) {
            //Warning, sessions enabled, disable them after check, $_SESSION is in read-only mode after disable
            static::sessionStart();
            $_session =& $_SESSION;
        }

        $this->setAuthKeysIfExists();

        $auth               = AuthenticationHelper::getInstance( $_session, $this->api_key, $this->api_secret );
        $this->user         = $auth->getUser();
        $this->userIsLogged = $auth->isLogged();
        $this->api_record   = $auth->getApiRecord();

    }

    /**
     * @return void
     */
    protected function setAuthKeysIfExists(): void {

        $headers = array_change_key_case( getallheaders() );

        $this->api_key    = $headers[ 'x-matecat-key' ] ?? base64_decode( explode( 'Bearer ', $headers[ 'authorization' ] ?? '' )[ 1 ] ?? null );
        $this->api_secret = $headers[ 'x-matecat-secret' ] ?? null;

        if ( false !== strpos( $this->api_key, '-' ) ) {
            [ $this->api_key, $this->api_secret ] = explode( '-', $this->api_key );
        }

    }

    public function isLoggedIn(): bool {
        return $this->userIsLogged;
    }

    /**
     * @return ?UserStruct
     */
    public function getUser(): UserStruct {
        return $this->user;
    }

    public function broadcastLogout() {
        $this->logout();
        $queueHandler = new AMQHandler();
        $message      = json_encode( [
                '_type' => 'logout',
                'data'  => [
                        'uid'     => $this->user->uid,
                        'payload' => [
                                'uid' => $this->user->uid,
                        ]
                ]
        ] );
        $queueHandler->publishToNodeJsClients( AppConfig::$SOCKET_NOTIFICATIONS_QUEUE_NAME, new Message( $message ) );
    }

    public function logout() {
        AuthenticationHelper::destroyAuthentication( $_SESSION );
    }

    public function getApiRecord(): ?ApiKeyStruct {
        return $this->api_record;
    }

}