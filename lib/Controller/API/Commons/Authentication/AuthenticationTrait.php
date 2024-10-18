<?php

namespace API\Commons\Authentication;

use AMQHandler;
use Bootstrap;
use Exception;
use INIT;
use ReflectionException;
use Stomp\Transport\Message;
use Users_UserStruct;

/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 19/09/24
 * Time: 15:00
 *
 */
trait AuthenticationTrait {

    protected bool              $userIsLogged;
    protected ?Users_UserStruct $user = null;

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function identifyUser( ?bool $useSession = true, ?string $api_key = null, ?string $api_secret = null ) {

        $_session = [];
        if ( $useSession ) {
            //Warning, sessions enabled, disable them after check, $_SESSION is in read-only mode after disable
            Bootstrap::sessionStart();
            $_session =& $_SESSION;
        }

        $auth               = AuthenticationHelper::getInstance( $_session, $api_key, $api_secret );
        $this->user         = $auth->getUser();
        $this->userIsLogged = $auth->isLogged();

    }

    /**
     * Explicitly disable sessions for ajax call
     *
     * Sessions enabled on INIT Class
     *
     */
    public function disableSessions() {
        Bootstrap::sessionClose();
    }

    public function isLoggedIn(): bool {
        return $this->userIsLogged;
    }

    /**
     * @return ?Users_UserStruct
     */
    public function getUser(): ?Users_UserStruct {
        return $this->user;
    }

    public function broadcastLogout() {
        AuthenticationHelper::destroyAuthentication( $_SESSION );
        $queueHandler = new AMQHandler();
        $message      = json_encode( [
                '_type' => 'logout',
                'data'  => [
                        'uid'     => $this->user->uid,
                        'payload' => [
                                'uid'     => $this->user->uid,
                        ]
                ]
        ] );
        $queueHandler->publishToTopic( INIT::$SSE_NOTIFICATIONS_QUEUE_NAME, new Message( $message ) );
    }

}