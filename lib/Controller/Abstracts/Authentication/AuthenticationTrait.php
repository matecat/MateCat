<?php

namespace Controller\Abstracts\Authentication;

use Exception;
use Model\ApiKeys\ApiKeyStruct;
use Model\DataAccess\IDatabase;
use Model\Users\UserStruct;
use ReflectionException;
use Stomp\Exception\ConnectionException;
use Stomp\Transport\Message;
use TypeError;
use Utils\ActiveMQ\AMQHandler;
use Utils\Registry\AppConfig;
use Utils\Session\PhpSession;
use Utils\Session\PhpSessionStore;
use Utils\Session\SessionStore;
use Utils\Session\StatelessSessionStore;

/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 19/09/24
 * Time: 15:00
 *
 */
trait AuthenticationTrait
{

    /**
     * Provided by the host class (KleinController).
     */
    abstract public function getDatabase(): IDatabase;

    protected bool $userIsLogged;
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
     * The session store for this request. Set by identifyUser(), which is what decides whether this
     * controller is stateful at all.
     */
    protected SessionStore $sessionStore;

    /**
     * The store, defaulting to the refusing one when identifyUser() has not run.
     *
     * A typed property left uninitialised fails with `Cannot access uninitialized non-nullable
     * property`, which says nothing about the cause. Defaulting to StatelessSessionStore turns the
     * same mistake into the accurate message — this controller never established a session — and
     * keeps the invariant: no session state without identifyUser() having decided there is one.
     */
    protected function sessionStore(): SessionStore
    {
        return $this->sessionStore ??= new StatelessSessionStore();
    }

    /**
     * Build the authentication helper. Overridable seam for tests.
     */
    protected function buildAuthHelper(SessionStore $session, ?string $api_key = null, ?string $api_secret = null): AuthenticationHelper
    {
        return AuthenticationHelper::fromRequest($session, $this->getDatabase(), $api_key, $api_secret);
    }

    /**
     * Resolve the store for this request, and with it the stateful/stateless boundary.
     *
     * This used to be an empty local array for a stateless controller, which made "stateless" a
     * convention nothing enforced: the array absorbed writes and silently discarded them, and
     * nothing stopped the controller reading `$_SESSION` directly anyway. A store whose every
     * mutator throws turns that into an invariant the runtime holds.
     *
     * Overridable seam for tests.
     *
     * @throws Exception from PhpSession::start()
     */
    protected function buildSessionStore(bool $useSession): SessionStore
    {
        if (!$useSession) {
            return new StatelessSessionStore();
        }

        //Warning, sessions enabled, disable them after check, $_SESSION is in read-only mode after disabled
        PhpSession::start();

        return new PhpSessionStore();
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    protected function identifyUser(?bool $useSession = true): void
    {
        $this->sessionStore = $this->buildSessionStore((bool)$useSession);

        $this->setAuthKeysIfExists();

        $auth = $this->buildAuthHelper($this->sessionStore, $this->api_key, $this->api_secret);
        $this->user = $auth->getUser();
        $this->userIsLogged = $auth->isLogged();
        $this->api_record = $auth->getApiRecord();
    }

    /**
     * @return void
     */
    /**
     * @return void
     */
    protected function setAuthKeysIfExists(): void
    {
        /** @var array<string, string> $headers */
        $headers = array_change_key_case(getallheaders());

        $decoded = base64_decode(explode('Bearer ', $headers['authorization'] ?? '')[1] ?? '');
        $this->api_key = $headers['x-matecat-key'] ?? ($decoded !== false ? $decoded : null);
        $this->api_secret = $headers['x-matecat-secret'] ?? null;

        if ($this->api_key !== null && str_contains($this->api_key, '-')) {
            [$this->api_key, $this->api_secret] = explode('-', $this->api_key);
        }
    }

    public function isLoggedIn(): bool
    {
        return $this->userIsLogged;
    }

    /**
     * @return UserStruct
     */
    public function getUser(): UserStruct
    {
        return $this->user;
    }

    /**
     * @throws ReflectionException
     * @throws ConnectionException
     * @throws Exception
     * @throws TypeError
     */
    public function broadcastLogout(?AMQHandler $amqHandler = null): void
    {
        $this->logout();
        $queueHandler = $amqHandler ?? new AMQHandler();
        $message = json_encode([
            '_type' => 'logout',
            'data' => [
                'uid' => $this->user->uid,
                'payload' => [
                    'uid' => $this->user->uid,
                ]
            ]
        ]);

        if ($message === false) {
            return;
        }

        $queueHandler->publishToNodeJsClients(AppConfig::$SOCKET_NOTIFICATIONS_QUEUE_NAME, new Message($message));
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function logout(): void
    {
        $this->buildAuthHelper($this->sessionStore())->destroyAuthentication();
    }

    public function getApiRecord(): ?ApiKeyStruct
    {
        return $this->api_record;
    }

}