<?php

namespace Matecat\Core\Model\ConnectedServices\Oauth\Google;

use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\Oauth\Google\AccessToken;
use PHPUnit\Framework\Attributes\Test;

class AccessTokenTest extends AbstractTest
{
    #[Test]
    public function constructorStoresOriginalValues(): void
    {
        $options = [
            'access_token' => 'ya29.test',
            'expires_in' => 3600,
            'refresh_token' => 'refresh123',
            'scope' => 'email profile',
            'token_type' => 'Bearer',
            'id_token' => 'eyJhbGciOi...',
            'created' => 1700000000,
        ];

        $token = new AccessToken($options);

        $this->assertSame('ya29.test', $token->getToken());
    }

    #[Test]
    public function toArrayReturnsCorrectStructure(): void
    {
        $options = [
            'access_token' => 'ya29.test',
            'expires_in' => 3600,
            'refresh_token' => 'refresh123',
            'scope' => 'email profile',
            'token_type' => 'Bearer',
            'id_token' => 'eyJhbGciOi...',
            'created' => 1700000000,
        ];

        $token = new AccessToken($options);
        $array = $token->__toArray();

        $this->assertSame('ya29.test', $array['access_token']);
        $this->assertSame(3600, $array['expires_in']);
        $this->assertSame('refresh123', $array['refresh_token']);
        $this->assertSame('email profile', $array['scope']);
        $this->assertSame('Bearer', $array['token_type']);
        $this->assertSame('eyJhbGciOi...', $array['id_token']);
        $this->assertSame(1700000000, $array['created']);
        $this->assertCount(7, $array);
    }
}
