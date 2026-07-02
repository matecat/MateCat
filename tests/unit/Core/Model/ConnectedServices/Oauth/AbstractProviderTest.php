<?php

namespace Matecat\Core\Model\ConnectedServices\Oauth;

use League\OAuth2\Client\Token\AccessToken;
use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\Oauth\AbstractProvider;
use Model\ConnectedServices\Oauth\ProviderUser;
use PHPUnit\Framework\Attributes\Test;

class ConcreteTestProvider extends AbstractProvider
{
    const string PROVIDER_NAME = 'test_provider';

    public function getAuthorizationUrl(string $csrfTokenState): string
    {
        return 'https://example.com/auth?state=' . $csrfTokenState;
    }

    public function getAccessTokenFromAuthCode(string $code): AccessToken
    {
        return new AccessToken(['access_token' => 'test', 'expires_in' => 3600]);
    }

    public function getResourceOwner(AccessToken $token): ProviderUser
    {
        return new ProviderUser();
    }

    public function getClient(?string $redirectUrl = null): mixed
    {
        return null;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }
}

class AbstractProviderTest extends AbstractTest
{
    #[Test]
    public function constructorWithNullRedirectUrl(): void
    {
        $provider = new ConcreteTestProvider();
        $this->assertNull($provider->getRedirectUrl());
    }

    #[Test]
    public function constructorWithRedirectUrl(): void
    {
        $provider = new ConcreteTestProvider('https://example.com/callback');
        $this->assertSame('https://example.com/callback', $provider->getRedirectUrl());
    }

    #[Test]
    public function providerNameConstant(): void
    {
        $this->assertSame('test_provider', ConcreteTestProvider::PROVIDER_NAME);
    }

    #[Test]
    public function baseProviderNameIsEmpty(): void
    {
        $this->assertSame('', AbstractProvider::PROVIDER_NAME);
    }
}
