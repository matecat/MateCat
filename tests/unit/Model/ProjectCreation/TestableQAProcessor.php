<?php

namespace unit\Model\ProjectCreation;

use Matecat\ICU\MessagePatternComparator;
use Model\ProjectCreation\QAProcessor;
use Utils\LQA\QA;

/**
 * Testable subclass of QAProcessor that allows injecting a QA stub.
 */
class TestableQAProcessor extends QAProcessor
{
    private ?QA $qaInstance = null;

    /** @var ?MessagePatternComparator Captures the comparator passed to the last createQA() call */
    public ?MessagePatternComparator $lastComparator = null;

    /** @var ?bool Captures the sourceContainsIcu flag passed to the last createQA() call */
    public ?bool $lastSourceContainsIcu = null;

    public function setQA(QA $qa): void
    {
        $this->qaInstance = $qa;
    }

    protected function createQA(
        string $source,
        string $target,
        ?MessagePatternComparator $comparator = null,
        bool $sourceContainsIcu = false,
    ): QA {
        $this->lastComparator        = $comparator;
        $this->lastSourceContainsIcu = $sourceContainsIcu;

        return $this->qaInstance ?? parent::createQA($source, $target, $comparator, $sourceContainsIcu);
    }
}
