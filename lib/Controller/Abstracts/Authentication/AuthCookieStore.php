<?php

namespace Controller\Abstracts\Authentication;

use Exception;
use Model\Users\UserStruct;
use ReflectionException;
use TypeError;

/**
 * Thin instance wrapper around the static {@see AuthCookie} API, binding a
 * single {@see SessionTokenStoreHandler}. Turns the static cookie/session
 * calls into a mockable collaborator.
 */
class AuthCookieStore
{
    public function __construct(private readonly SessionTokenStoreHandler $tokenStore)
    {
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function getCredentials(): ?array
    {
        return AuthCookie::getCredentials($this->tokenStore);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function setCredentials(UserStruct $user, bool $isLoginCookieRevamp = false): void
    {
        AuthCookie::setCredentials($user, $this->tokenStore, $isLoginCookieRevamp);
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     * @throws TypeError
     */
    public function destroy(): void
    {
        AuthCookie::destroyAuthentication($this->tokenStore);
    }
}
