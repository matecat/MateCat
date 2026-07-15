<?php

namespace Matecat\Core\Controller\API\Commons\Exceptions;

use Controller\API\Commons\Exceptions\ValidationError;
use Exception;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

class ValidationErrorTest extends AbstractTest
{
    #[Test]
    public function isAThrowableException(): void
    {
        $e = new ValidationError();

        $this->assertInstanceOf(Exception::class, $e);
        $this->assertInstanceOf(Throwable::class, $e);
    }

    #[Test]
    public function carriesMessageAndCode(): void
    {
        $previous = new Exception('root');
        $e = new ValidationError('issue not found', -2000, $previous);

        $this->assertSame('issue not found', $e->getMessage());
        $this->assertSame(-2000, $e->getCode());
        $this->assertSame($previous, $e->getPrevious());
    }
}
