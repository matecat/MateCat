<?php

namespace Matecat\Core\Controllers\Authentication;

use Controller\Abstracts\Authentication\AuthCookieStore;
use Controller\Abstracts\Authentication\SessionTokenStoreHandler;
use Matecat\TestHelpers\AbstractTest;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(AuthCookieStore::class)]
class AuthCookieStoreTest extends AbstractTest
{
    #[Test]
    public function getCredentialsReturnsNullWhenNoCookiePresent(): void
    {
        $store = new AuthCookieStore(new SessionTokenStoreHandler());

        // Clean environment: no login cookie set → no credentials.
        $this->assertNull($store->getCredentials());
    }

    #[Test]
    public function setCredentialsForwardsToAuthCookieWithBoundTokenStore(): void
    {
        $user        = new UserStruct();
        $user->uid   = 7;
        $user->email = 'store@example.com';

        $tokenStore = $this->createMock(SessionTokenStoreHandler::class);
        // New login (revamp=false) activates the token via the bound handler.
        $tokenStore->expects($this->once())->method('setCookieLoginTokenActive');

        (new AuthCookieStore($tokenStore))->setCredentials($user, false);
    }

    #[Test]
    public function destroyForwardsToAuthCookieWithBoundTokenStore(): void
    {
        $tokenStore = $this->createMock(SessionTokenStoreHandler::class);
        // Destroy removes the login cookie from the bound handler.
        $tokenStore->expects($this->once())->method('removeLoginCookieFromStore');

        (new AuthCookieStore($tokenStore))->destroy();
    }
}
