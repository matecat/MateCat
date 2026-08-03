<?php

namespace Controller\Abstracts;

use Utils\Session\SessionStore;

/**
 * One-shot messages carried across a redirect.
 *
 * Instance rather than static because the store has to come from somewhere, and a static setter for
 * it would be the global mutable state this refactor exists to remove — the same trap
 * `AuthCookie::setCookieManager()` was, where `tearDown()` had to remember to reset it or the next
 * test inherited it.
 *
 * These stay on the session store rather than moving to the uid-keyed one: a flash message is bound
 * to a browser, not to a user, and is routinely set on redirects where no uid exists yet.
 */
class FlashMessage
{

    const string KEY = 'flashMessages';

    const string WARNING = 'warning';
    const string ERROR = 'error';
    const string INFO = 'info';
    const string SERVICE = 'service';

    private SessionStore $session;

    public function __construct(SessionStore $session)
    {
        $this->session = $session;
    }

    public function set(string $key, string $value, string $type = self::WARNING): void
    {
        $messages = $this->session->get(self::KEY);

        if (!is_array($messages)) {
            $messages = [
                self::WARNING => [],
                self::ERROR => [],
                self::INFO => []
            ];
        }

        $messages[$type][] = [
            'key' => $key,
            'value' => $value
        ];

        $this->session->set(self::KEY, $messages);
    }

    /**
     * Read and clear in one step: a flash message that survived its own read would be shown twice.
     *
     * @return array<string, array<int, array{key: string, value: string}>>|null
     */
    public function flush(): ?array
    {
        $messages = $this->session->get(self::KEY);

        if (!is_array($messages)) {
            return null;
        }

        $this->session->remove(self::KEY);

        /** @var array<string, array<int, array{key: string, value: string}>> $messages */
        return $messages;
    }

}
