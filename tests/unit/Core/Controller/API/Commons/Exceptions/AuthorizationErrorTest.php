<?php

namespace Matecat\Core\Controller\API\Commons\Exceptions;

use Controller\API\Commons\Exceptions\AuthorizationError;
use Exception;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

class AuthorizationErrorTest extends AbstractTest
{
    #[Test]
    public function isAThrowableException(): void
    {
        $e = new AuthorizationError();

        $this->assertInstanceOf(Exception::class, $e);
        $this->assertInstanceOf(Throwable::class, $e);
    }

    #[Test]
    public function carriesMessageAndCode(): void
    {
        $previous = new Exception('root');
        $e = new AuthorizationError('Not Authorized', 403, $previous);

        $this->assertSame('Not Authorized', $e->getMessage());
        $this->assertSame(403, $e->getCode());
        $this->assertSame($previous, $e->getPrevious());
    }
}
