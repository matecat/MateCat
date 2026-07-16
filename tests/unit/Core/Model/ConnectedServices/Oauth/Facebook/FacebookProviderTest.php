<?php

namespace Matecat\Core\Model\ConnectedServices\Oauth\Facebook;

use League\OAuth2\Client\Provider\Facebook;
use League\OAuth2\Client\Provider\FacebookUser;
use League\OAuth2\Client\Token\AccessToken;
use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\Oauth\Facebook\FacebookProvider;
use Model\ConnectedServices\Oauth\ProviderUser;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class TestableFacebookProvider extends FacebookProvider
{
    private ?Facebook $mockClient = null;

    public function setMockClient(Facebook $client): void
    {
        $this->mockClient = $client;
    }

    public function getClient(?string $redirectUrl = null): Facebook
    {
        if ($this->mockClient !== null) {
            return $this->mockClient;
        }
        return parent::getClient($redirectUrl);
    }
}

class FacebookProviderTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();
        AppConfig::$FACEBOOK_OAUTH_CLIENT_ID = 'test-fb-id';
        AppConfig::$FACEBOOK_OAUTH_CLIENT_SECRET = 'test-fb-secret';
        AppConfig::$FACEBOOK_OAUTH_REDIRECT_URL = 'http://localhost/fb/callback';
    }

    #[Test]
    public function providerName(): void
    {
        $this->assertSame('facebook', FacebookProvider::PROVIDER_NAME);
    }

    #[Test]
    public function getClientReturnsFacebookInstance(): void
    {
        $provider = new FacebookProvider();
        $client = $provider->getClient();

        $this->assertInstanceOf(Facebook::class, $client);
    }

    #[Test]
    public function getClientUsesCustomRedirectUrl(): void
    {
        $provider = new FacebookProvider();
        $client = $provider->getClient('http://custom/callback');

        $this->assertInstanceOf(Facebook::class, $client);
    }

    #[Test]
    public function getAuthorizationUrlReturnsFacebookUrl(): void
    {
        $provider = new FacebookProvider('http://localhost/fb/callback');
        $url = $provider->getAuthorizationUrl('csrf-token');

        $this->assertStringContainsString('facebook.com', $url);
        $this->assertStringContainsString('csrf-token', $url);
    }

    #[Test]
    public function getAccessTokenFromAuthCodeReturnsToken(): void
    {
        $expectedToken = new AccessToken(['access_token' => 'fb-token', 'expires_in' => 3600]);

        $mockClient = $this->createStub(Facebook::class);
        $mockClient->method('getAccessToken')->willReturn($expectedToken);

        $provider = new TestableFacebookProvider('http://localhost/fb/callback');
        $provider->setMockClient($mockClient);

        $token = $provider->getAccessTokenFromAuthCode('auth-code');

        $this->assertSame('fb-token', $token->getToken());
    }

    #[Test]
    public function getResourceOwnerReturnsProviderUser(): void
    {
        $token = new AccessToken(['access_token' => 'fb-token', 'expires_in' => 3600]);

        $fbUser = $this->createStub(FacebookUser::class);
        $fbUser->method('getEmail')->willReturn('user@facebook.com');
        $fbUser->method('getFirstName')->willReturn('Jane');
        $fbUser->method('getLastName')->willReturn('Smith');
        $fbUser->method('getPictureUrl')->willReturn('https://fb.com/pic.jpg');

        $mockClient = $this->createStub(Facebook::class);
        $mockClient->method('getResourceOwner')->willReturn($fbUser);

        $provider = new TestableFacebookProvider('http://localhost/fb/callback');
        $provider->setMockClient($mockClient);

        $user = $provider->getResourceOwner($token);

        $this->assertInstanceOf(ProviderUser::class, $user);
        $this->assertSame('user@facebook.com', $user->email);
        $this->assertSame('Jane', $user->name);
        $this->assertSame('Smith', $user->lastName);
        $this->assertSame('https://fb.com/pic.jpg', $user->picture);
        $this->assertSame('facebook', $user->provider);
    }

    #[Test]
    public function getResourceOwnerThrowsOnMissingEmail(): void
    {
        $token = new AccessToken(['access_token' => 'fb-token', 'expires_in' => 3600]);

        $fbUser = $this->createStub(FacebookUser::class);
        $fbUser->method('getEmail')->willReturn(null);
        $fbUser->method('getFirstName')->willReturn('Jane');

        $mockClient = $this->createStub(Facebook::class);
        $mockClient->method('getResourceOwner')->willReturn($fbUser);

        $provider = new TestableFacebookProvider('http://localhost/fb/callback');
        $provider->setMockClient($mockClient);

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('email is required');

        $provider->getResourceOwner($token);
    }
}
