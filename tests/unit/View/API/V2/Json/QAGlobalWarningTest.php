<?php

namespace unit\View\API\V2\Json;

use Model\Warnings\GlobalWarningStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use TestHelpers\AbstractTest;
use Utils\LQA\QA;
use View\API\V2\Json\QAGlobalWarning;

#[CoversClass(QAGlobalWarning::class)]
class QAGlobalWarningTest extends AbstractTest
{
    private function makeGlobalWarningStruct(string $serializedErrors, string $idSegment = '1'): GlobalWarningStruct
    {
        $struct                         = new GlobalWarningStruct();
        $struct->serialized_errors_list = $serializedErrors;
        $struct->id_segment             = $idSegment;

        return $struct;
    }

    public function testRenderWithEmptyInputsReturnsDetailsKey(): void
    {
        $qa     = new QAGlobalWarning([], []);
        $result = $qa->render();

        $this->assertArrayHasKey('details', $result);
    }

    public function testRenderWithEmptyInputsHasErrorWarningInfoKeys(): void
    {
        $qa     = new QAGlobalWarning([], []);
        $result = $qa->render();

        $this->assertArrayHasKey(QA::ERROR, $result['details']);
        $this->assertArrayHasKey(QA::WARNING, $result['details']);
        $this->assertArrayHasKey(QA::INFO, $result['details']);
    }

    public function testRenderWithEmptyInputsHasCategoriesKey(): void
    {
        $qa     = new QAGlobalWarning([], []);
        $result = $qa->render();

        $this->assertArrayHasKey('Categories', $result['details'][QA::ERROR]);
        $this->assertArrayHasKey('Categories', $result['details'][QA::WARNING]);
        $this->assertArrayHasKey('Categories', $result['details'][QA::INFO]);
    }

    public function testRenderWithNoErrorsHasEmptyCategories(): void
    {
        $struct = $this->makeGlobalWarningStruct('[]');

        $qa     = new QAGlobalWarning([$struct], []);
        $result = $qa->render();

        $this->assertEmpty($result['details'][QA::ERROR]['Categories']);
        $this->assertEmpty($result['details'][QA::WARNING]['Categories']);
    }

    public function testRenderTranslationMismatchesAppendedToWarning(): void
    {
        $qa     = new QAGlobalWarning([], [['first_of_my_job' => 'mismatch_seg_10']]);
        $result = $qa->render();

        $this->assertArrayHasKey('MISMATCH', $result['details'][QA::WARNING]['Categories']);
        $this->assertContains('mismatch_seg_10', $result['details'][QA::WARNING]['Categories']['MISMATCH']);
    }

    public function testRenderTranslationMismatchesSkipsEmptyFirstOfMyJob(): void
    {
        $qa     = new QAGlobalWarning([], [['first_of_my_job' => '']]);
        $result = $qa->render();

        $this->assertArrayNotHasKey('MISMATCH', $result['details'][QA::WARNING]['Categories']);
    }

    public function testRenderTranslationMismatchesSkipsMissingFirstOfMyJob(): void
    {
        $qa     = new QAGlobalWarning([], [[]]);
        $result = $qa->render();

        $this->assertArrayNotHasKey('MISMATCH', $result['details'][QA::WARNING]['Categories']);
    }

    public function testRenderMultipleTranslationMismatchesCollected(): void
    {
        $qa     = new QAGlobalWarning([], [
            ['first_of_my_job' => 'seg_1'],
            ['first_of_my_job' => 'seg_2'],
        ]);
        $result = $qa->render();

        $mismatch = $result['details'][QA::WARNING]['Categories']['MISMATCH'];
        $this->assertContains('seg_1', $mismatch);
        $this->assertContains('seg_2', $mismatch);
    }
}
