<?php

use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class ExecutorRedisExceptionTest extends AbstractTest
{
    #[Test]
    public function test_executor_handles_predis_connection_exception_before_throwable_and_requeues(): void
    {
        $executorPath = realpath(__DIR__ . '/../../../lib/Utils/TaskRunner/Executor.php');
        $this->assertNotFalse($executorPath);

        $source = file_get_contents($executorPath);
        $this->assertNotFalse($source);

        $predisCatchPos = strpos($source, 'catch (\\Predis\\Connection\\ConnectionException|\\Predis\\Response\\ServerException $e)');
        $this->assertNotFalse($predisCatchPos, 'Expected Predis catch block in Executor::main().');

        $throwableCatchPos = strpos($source, 'catch (Throwable $e)');
        $this->assertNotFalse($throwableCatchPos, 'Expected Throwable catch block in Executor::main().');

        $this->assertLessThan(
            $throwableCatchPos,
            $predisCatchPos,
            'Predis catch block must appear before Throwable catch block.'
        );

        $predisCatchBody = substr($source, $predisCatchPos, $throwableCatchPos - $predisCatchPos);

        $this->assertStringContainsString(
            'reQueue(',
            $predisCatchBody,
            'Predis catch block must reQueue queue elements.'
        );
    }
}
