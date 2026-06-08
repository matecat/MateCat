<?php

namespace Matecat\Core\Model\ConnectedServices\Oauth\Github;

use League\OAuth2\Client\Provider\Github;
use League\OAuth2\Client\Provider\GithubResourceOwner;
use League\OAuth2\Client\Token\AccessToken;
use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\Oauth\Github\GithubProvider;
use Model\ConnectedServices\Oauth\ProviderUser;
use PHPUnit\Framework\Attributes\Test;
use Utils\Registry\AppConfig;

class TestableGithubProvider extends GithubProvider
{
    private ?Github $mockClient = null;

    public function setMockClient(Github $client): void
    {
        $this->mockClient = $client;
    }

    public function getClient(?string $redirectUrl = null): Github
    {
        if ($this->mockClient !== null) {
            return $this->mockClient;
        }
        return parent::getClient($redirectUrl);
    }
}

class GithubProviderTest extends AbstractTest
{
    protected function setUp(): void
    {
        parent::setUp();
        AppConfig::$GITHUB_OAUTH_CLIENT_ID = 'test-gh-id';
        AppConfig::$GITHUB_OAUTH_CLIENT_SECRET = 'test-gh-secret';
        AppConfig::$GITHUB_OAUTH_REDIRECT_URL = 'http://localhost/gh/callback';
    }

    #[Test]
    public function providerName(): void
    {
        $this->assertSame('github', GithubProvider::PROVIDER_NAME);
    }

    #[Test]
    public function getClientReturnsGithubInstance(): void
    {
        $provider = new GithubProvider();
        $client = $provider->getClient();

        $this->assertInstanceOf(Github::class, $client);
    }

    #[Test]
    public function getClientUsesCustomRedirectUrl(): void
    {
        $provider = new GithubProvider();
        $client = $provider->getClient('http://custom/callback');

        $this->assertInstanceOf(Github::class, $client);
    }

    #[Test]
    public function getAuthorizationUrlReturnsGithubUrl(): void
    {
        $provider = new GithubProvider('http://localhost/gh/callback');
        $url = $provider->getAuthorizationUrl('csrf-token');

        $this->assertStringContainsString('github.com', $url);
        $this->assertStringContainsString('csrf-token', $url);
    }

    #[Test]
    public function getAccessTokenFromAuthCodeReturnsToken(): void
    {
        $expectedToken = new AccessToken(['access_token' => 'gh-token', 'expires_in' => 3600]);

        $mockClient = $this->createStub(Github::class);
        $mockClient->method('getAccessToken')->willReturn($expectedToken);

        $provider = new TestableGithubProvider();
        $provider->setMockClient($mockClient);

        $token = $provider->getAccessTokenFromAuthCode('auth-code');

        $this->assertSame('gh-token', $token->getToken());
    }

    #[Test]
    public function getResourceOwnerReturnsProviderUser(): void
    {
        $token = new AccessToken(['access_token' => 'gh-token', 'expires_in' => 3600]);

        $ghUser = $this->createStub(GithubResourceOwner::class);
        $ghUser->method('toArray')->willReturn([
            'name' => 'John Doe',
            'email' => 'john@github.com',
            'avatar_url' => 'https://github.com/avatar.jpg',
        ]);

        $mockClient = $this->createStub(Github::class);
        $mockClient->method('getResourceOwner')->willReturn($ghUser);

        $provider = new TestableGithubProvider('http://localhost/gh/callback');
        $provider->setMockClient($mockClient);

        $user = $provider->getResourceOwner($token);

        $this->assertInstanceOf(ProviderUser::class, $user);
        $this->assertSame('john@github.com', $user->email);
        $this->assertSame('John', $user->name);
        $this->assertSame('Doe', $user->lastName);
        $this->assertSame('https://github.com/avatar.jpg', $user->picture);
        $this->assertSame('github', $user->provider);
    }

    #[Test]
    public function getResourceOwnerThrowsOnMissingName(): void
    {
        $token = new AccessToken(['access_token' => 'gh-token', 'expires_in' => 3600]);

        $ghUser = $this->createStub(GithubResourceOwner::class);
        $ghUser->method('toArray')->willReturn(['email' => 'john@github.com']);

        $mockClient = $this->createStub(Github::class);
        $mockClient->method('getResourceOwner')->willReturn($ghUser);

        $provider = new TestableGithubProvider('http://localhost/gh/callback');
        $provider->setMockClient($mockClient);

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('name is required');

        $provider->getResourceOwner($token);
    }

    #[Test]
    public function getResourceOwnerHandlesSingleName(): void
    {
        $token = new AccessToken(['access_token' => 'gh-token', 'expires_in' => 3600]);

        $ghUser = $this->createStub(GithubResourceOwner::class);
        $ghUser->method('toArray')->willReturn([
            'name' => 'Mononym',
            'email' => 'mono@github.com',
        ]);

        $mockClient = $this->createStub(Github::class);
        $mockClient->method('getResourceOwner')->willReturn($ghUser);

        $provider = new TestableGithubProvider('http://localhost/gh/callback');
        $provider->setMockClient($mockClient);

        $user = $provider->getResourceOwner($token);

        $this->assertSame('Mononym', $user->name);
        $this->assertNull($user->lastName);
    }
}
