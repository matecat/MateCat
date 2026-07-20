<?php

namespace Matecat\Core\Controller\API\Commons\Exceptions;

use Controller\API\Commons\Exceptions\UnprocessableException;
use Exception;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

class UnprocessableExceptionTest extends AbstractTest
{
    #[Test]
    public function defaultsToEmptyMessageAndUnprocessableCode(): void
    {
        $e = new UnprocessableException();

        $this->assertInstanceOf(Exception::class, $e);
        $this->assertInstanceOf(Throwable::class, $e);
        $this->assertSame('', $e->getMessage());
        $this->assertSame(422, $e->getCode());
    }

    #[Test]
    public function acceptsCustomMessageCodeAndPrevious(): void
    {
        $previous = new Exception('root');
        $e = new UnprocessableException('cannot process entity', 418, $previous);

        $this->assertSame('cannot process entity', $e->getMessage());
        $this->assertSame(418, $e->getCode());
        $this->assertSame($previous, $e->getPrevious());
    }
}
