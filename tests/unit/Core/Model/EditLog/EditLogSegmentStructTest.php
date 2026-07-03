<?php

namespace Matecat\Core\Model\EditLog;

use Matecat\TestHelpers\AbstractTest;
use Model\EditLog\EditLogSegmentStruct;
use PHPUnit\Framework\Attributes\Test;

class EditLogSegmentStructTest extends AbstractTest
{
    // ─── getSecsPerWord() ───

    #[Test]
    public function getSecsPerWordReturnsCorrectValue(): void
    {
        $struct = new EditLogSegmentStruct();
        $struct->time_to_edit = 5000;
        $struct->raw_word_count = 10;

        $this->assertSame(0.5, $struct->getSecsPerWord());
    }

    #[Test]
    public function getSecsPerWordThrowsForZeroWordCount(): void
    {
        $struct = new EditLogSegmentStruct();
        $struct->time_to_edit = 5000;
        $struct->raw_word_count = 0;

        $this->expectException(\DivisionByZeroError::class);
        $struct->getSecsPerWord();
    }

    #[Test]
    public function getSecsPerWordReturnsZeroForZeroTime(): void
    {
        $struct = new EditLogSegmentStruct();
        $struct->time_to_edit = 0;
        $struct->raw_word_count = 10;

        $this->assertSame(0.0, $struct->getSecsPerWord());
    }

    // ─── isValidForEditLog() ───

    #[Test]
    public function isValidForEditLogReturnsTrueForValidRange(): void
    {
        $struct = new EditLogSegmentStruct();
        $struct->time_to_edit = 5000;
        $struct->raw_word_count = 1;

        $this->assertTrue($struct->isValidForEditLog());
    }

    #[Test]
    public function isValidForEditLogReturnsFalseForTooFast(): void
    {
        $struct = new EditLogSegmentStruct();
        $struct->time_to_edit = 100;
        $struct->raw_word_count = 10;

        $this->assertFalse($struct->isValidForEditLog());
    }

    #[Test]
    public function isValidForEditLogReturnsFalseForTooSlow(): void
    {
        $struct = new EditLogSegmentStruct();
        $struct->time_to_edit = 300000;
        $struct->raw_word_count = 1;

        $this->assertFalse($struct->isValidForEditLog());
    }

    // ─── getPEE() ───

    #[Test]
    public function getPEEReturnsZeroForEmptySuggestion(): void
    {
        $struct = new EditLogSegmentStruct();
        $struct->suggestion = null;
        $struct->translation = 'Hello';

        $this->assertSame(0.0, $struct->getPEE());
    }

    #[Test]
    public function getPEEReturnsZeroForEmptyTranslation(): void
    {
        $struct = new EditLogSegmentStruct();
        $struct->suggestion = 'Hello';
        $struct->translation = null;

        $this->assertSame(0.0, $struct->getPEE());
    }

    #[Test]
    public function getPEEReturnsZeroForIdenticalStrings(): void
    {
        $struct = new EditLogSegmentStruct();
        $struct->suggestion = 'Hello world';
        $struct->translation = 'Hello world';
        $struct->target_language = 'en-US';

        $this->assertSame(0.0, $struct->getPEE());
    }

    #[Test]
    public function getPEEReturnsNonZeroForDifferentStrings(): void
    {
        $struct = new EditLogSegmentStruct();
        $struct->suggestion = 'Hello world';
        $struct->translation = 'Goodbye world';
        $struct->target_language = 'en-US';

        $this->assertGreaterThan(0.0, $struct->getPEE());
    }

    // ─── constants ───

    #[Test]
    public function constantsHaveExpectedValues(): void
    {
        $this->assertSame(30, EditLogSegmentStruct::EDIT_TIME_SLOW_CUT);
        $this->assertSame(0.25, EditLogSegmentStruct::EDIT_TIME_FAST_CUT);
    }
}
