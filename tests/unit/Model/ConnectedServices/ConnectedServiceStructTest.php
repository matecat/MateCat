<?php

namespace unit\Model\ConnectedServices;

use Model\ConnectedServices\ConnectedServiceStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

/**
 * @covers \Model\ConnectedServices\ConnectedServiceStruct
 */
class ConnectedServiceStructTest extends AbstractTest
{
    #[Test]
    public function getDecryptedOauthAccessTokenReturnsNullWhenTokenIsNull(): void
    {
        $struct = new ConnectedServiceStruct();
        $struct->oauth_access_token = null;

        $result = $struct->getDecryptedOauthAccessToken();

        $this->assertNull($result);
    }

    #[Test]
    public function getDecodedOauthAccessTokenReturnsNullWhenDecryptedTokenIsNull(): void
    {
        $struct = new ConnectedServiceStruct();
        $struct->oauth_access_token = null;

        $result = $struct->getDecodedOauthAccessToken();

        $this->assertNull($result);
    }

    #[Test]
    public function getDecodedOauthAccessTokenReturnsNullWhenFieldRequestedButTokenIsNull(): void
    {
        $struct = new ConnectedServiceStruct();
        $struct->oauth_access_token = null;

        $result = $struct->getDecodedOauthAccessToken('some_field');

        $this->assertNull($result);
    }

    #[Test]
    public function structHasExpectedPublicProperties(): void
    {
        $struct = new ConnectedServiceStruct([
            'id'         => 1,
            'uid'        => 100,
            'service'    => 'gdrive',
            'email'      => 'user@example.com',
            'name'       => 'Test User',
            'created_at' => '2024-01-01 00:00:00',
        ]);

        $this->assertSame(1, $struct->id);
        $this->assertSame(100, $struct->uid);
        $this->assertSame('gdrive', $struct->service);
        $this->assertSame('user@example.com', $struct->email);
        $this->assertSame('Test User', $struct->name);
        $this->assertNull($struct->disabled_at);
        $this->assertSame(1, $struct->is_default);
    }
}
