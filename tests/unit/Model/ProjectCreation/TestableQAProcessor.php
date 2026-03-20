<?php

namespace unit\Model\ProjectCreation;

use Model\ProjectCreation\QAProcessor;
use Utils\LQA\QA;

/**
 * Testable subclass of QAProcessor that allows injecting a QA stub.
 */
class TestableQAProcessor extends QAProcessor
{
    private ?QA $qaInstance = null;

    /**
     * Inject a QA stub/mock for tests.
     */
    public function setQA(QA $qa): void
    {
        $this->qaInstance = $qa;
    }

    /**
     * Override to return the injected QA instance instead of creating a real one.
     */
    protected function createQA(string $source, string $target): QA
    {
        return $this->qaInstance ?? parent::createQA($source, $target);
    }
}
