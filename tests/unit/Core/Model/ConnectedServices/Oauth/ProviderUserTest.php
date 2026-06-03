<?php

namespace Matecat\Core\Model\ConnectedServices\Oauth;

use Matecat\TestHelpers\AbstractTest;
use Model\ConnectedServices\Oauth\ProviderUser;
use PHPUnit\Framework\Attributes\Test;

class ProviderUserTest extends AbstractTest
{
    #[Test]
    public function defaultValues(): void
    {
        $user = new ProviderUser();

        $this->assertSame('', $user->name);
        $this->assertSame('', $user->lastName);
        $this->assertSame('', $user->email);
        $this->assertSame('', $user->authToken);
        $this->assertNull($user->picture);
        $this->assertSame('', $user->provider);
    }

    #[Test]
    public function setAllProperties(): void
    {
        $user = new ProviderUser();
        $user->name = 'John';
        $user->lastName = 'Doe';
        $user->email = 'john@example.com';
        $user->authToken = 'token123';
        $user->picture = 'https://example.com/pic.jpg';
        $user->provider = 'google';

        $this->assertSame('John', $user->name);
        $this->assertSame('Doe', $user->lastName);
        $this->assertSame('john@example.com', $user->email);
        $this->assertSame('token123', $user->authToken);
        $this->assertSame('https://example.com/pic.jpg', $user->picture);
        $this->assertSame('google', $user->provider);
    }
}
