<?php

namespace Matecat\Core\Model\ConnectedServices\Oauth\LinkedIn;

use League\OAuth2\Client\Token\AccessToken;
use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\Oauth\LinkedIn\LinkedinFinal;
use PHPUnit\Framework\Attributes\Test;

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
