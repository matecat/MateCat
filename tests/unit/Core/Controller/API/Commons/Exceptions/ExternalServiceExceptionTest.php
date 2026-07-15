<?php

namespace Matecat\Core\Controller\API\Commons\Exceptions;

use Controller\API\Commons\Exceptions\ExternalServiceException;
use Exception;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

class ExternalServiceExceptionTest extends AbstractTest
{
    #[Test]
    public function defaultsToEmptyMessageAndServiceUnavailableCode(): void
    {
        $e = new ExternalServiceException();

        $this->assertInstanceOf(Exception::class, $e);
        $this->assertInstanceOf(Throwable::class, $e);
        $this->assertSame('', $e->getMessage());
        $this->assertSame(503, $e->getCode());
    }

    #[Test]
    public function acceptsCustomMessageCodeAndPrevious(): void
    {
        $previous = new Exception('root');
        $e = new ExternalServiceException('upstream down', 502, $previous);

        $this->assertSame('upstream down', $e->getMessage());
        $this->assertSame(502, $e->getCode());
        $this->assertSame($previous, $e->getPrevious());
    }
}
