<?php

namespace Matecat\Core\Controller\API\Commons\Exceptions;

use Controller\API\Commons\Exceptions\AuthenticationError;
use Exception;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

class AuthenticationErrorTest extends AbstractTest
{
    #[Test]
    public function isAThrowableException(): void
    {
        $e = new AuthenticationError();

        $this->assertInstanceOf(Exception::class, $e);
        $this->assertInstanceOf(Throwable::class, $e);
    }

    #[Test]
    public function carriesMessageAndCode(): void
    {
        $previous = new Exception('root');
        $e = new AuthenticationError('Invalid API key', 401, $previous);

        $this->assertSame('Invalid API key', $e->getMessage());
        $this->assertSame(401, $e->getCode());
        $this->assertSame($previous, $e->getPrevious());
    }
}
