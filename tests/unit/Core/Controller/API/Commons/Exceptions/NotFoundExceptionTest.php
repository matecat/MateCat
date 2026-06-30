<?php

namespace Matecat\Core\Controller\API\Commons\Exceptions;

use Controller\API\Commons\Exceptions\NotFoundException;
use Exception;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

class NotFoundExceptionTest extends AbstractTest
{
    #[Test]
    public function defaultsToEmptyMessageAndNotFoundCode(): void
    {
        $e = new NotFoundException();

        $this->assertInstanceOf(\Model\Exceptions\NotFoundException::class, $e);
        $this->assertInstanceOf(Throwable::class, $e);
        $this->assertSame('', $e->getMessage());
        $this->assertSame(404, $e->getCode());
    }

    #[Test]
    public function acceptsCustomMessageCodeAndPrevious(): void
    {
        $previous = new Exception('root');
        $e = new NotFoundException('Project not found.', 410, $previous);

        $this->assertSame('Project not found.', $e->getMessage());
        $this->assertSame(410, $e->getCode());
        $this->assertSame($previous, $e->getPrevious());
    }
}
