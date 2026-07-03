<?php

namespace Matecat\Core\Controller\API\Commons\Exceptions;

use Controller\API\Commons\Exceptions\ConflictError;
use Exception;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

class ConflictErrorTest extends AbstractTest
{
    #[Test]
    public function isAThrowableException(): void
    {
        $e = new ConflictError();

        $this->assertInstanceOf(Exception::class, $e);
        $this->assertInstanceOf(Throwable::class, $e);
    }

    #[Test]
    public function carriesMessageAndCode(): void
    {
        $previous = new Exception('root');
        $e = new ConflictError('Conflict', 409, $previous);

        $this->assertSame('Conflict', $e->getMessage());
        $this->assertSame(409, $e->getCode());
        $this->assertSame($previous, $e->getPrevious());
    }
}
