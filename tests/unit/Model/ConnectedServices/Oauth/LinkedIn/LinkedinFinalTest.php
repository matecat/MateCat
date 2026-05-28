<?php

namespace unit\Model\ConnectedServices\Oauth\LinkedIn;

use League\OAuth2\Client\Token\AccessToken;
use Model\ConnectedServices\Oauth\LinkedIn\LinkedinFinal;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class LinkedinFinalTest extends AbstractTest
{
    private LinkedinFinal $linkedinFinal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->linkedinFinal = new LinkedinFinal([
            'clientId' => 'test-id',
            'clientSecret' => 'test-secret',
            'redirectUri' => 'http://localhost/callback',
        ]);
    }

    #[Test]
    public function getResourceOwnerDetailsUrlReturnsUserinfoEndpoint(): void
    {
        $token = new AccessToken(['access_token' => 'test-token', 'expires_in' => 3600]);

        $url = $this->linkedinFinal->getResourceOwnerDetailsUrl($token);

        $this->assertSame('https://api.linkedin.com/v2/userinfo', $url);
    }
}
