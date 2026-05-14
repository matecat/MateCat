<?php

namespace unit\TestWordCountCounter;

use BadMethodCallException;
use Exception;
use LogicException;
use Model\WordCount\CounterModel;
use Model\WordCount\WordCounterDao;
use Model\WordCount\WordCountStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Constants\TranslationStatus;

/**
 * @covers \Model\WordCount\CounterModel
 */
class CounterModelTest extends AbstractTest
{
    private function makeOldWCount(): WordCountStruct
    {
        $wc = new WordCountStruct();
        $wc->setIdJob(42);
        $wc->setJobPassword('abc123');
        $wc->setIdSegment(100);
        $wc->setNewWords(10.0);
        $wc->setDraftWords(5.0);
        $wc->setTranslatedWords(20.0);
        $wc->setApprovedWords(15.0);
        $wc->setApproved2Words(3.0);
        $wc->setRejectedWords(2.0);
        $wc->setNewRawWords(12);
        $wc->setDraftRawWords(6);
        $wc->setTranslatedRawWords(25);
        $wc->setApprovedRawWords(18);
        $wc->setApproved2RawWords(4);
        $wc->setRejectedRawWords(3);

        return $wc;
    }


    #[Test]
    public function constructorWithoutArgInitializesNullOldWCount(): void
    {
        $model = new CounterModel();
        $this->assertEmpty($model->getValues());
    }

    #[Test]
    public function constructorWithOldWCountSetsIt(): void
    {
        $wc = $this->makeOldWCount();
        $model = new CounterModel($wc);
        // Verify no exception when using getUpdatedValues (oldWCount is set)
        $model->setOldStatus(TranslationStatus::STATUS_NEW);
        $model->setNewStatus(TranslationStatus::STATUS_TRANSLATED);
        $result = $model->getUpdatedValues(10.0, 12);
        $this->assertInstanceOf(WordCountStruct::class, $result);
    }


    #[Test]
    public function setNewStatusWithValidStatusSucceeds(): void
    {
        $model = new CounterModel();
        $model->setNewStatus(TranslationStatus::STATUS_TRANSLATED);
        // No exception means success — verify via getUpdatedValues behavior
        $this->assertTrue(true);
    }

    #[Test]
    public function setOldStatusWithValidStatusSucceeds(): void
    {
        $model = new CounterModel();
        $model->setOldStatus(TranslationStatus::STATUS_DRAFT);
        $this->assertTrue(true);
    }

    #[Test]
    public function setNewStatusWithInvalidStatusThrowsBadMethodCall(): void
    {
        $model = new CounterModel();
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('INVALID_STATUS status is not defined');
        $model->setNewStatus('INVALID_STATUS');
    }

    #[Test]
    public function setOldStatusWithInvalidStatusThrowsBadMethodCall(): void
    {
        $model = new CounterModel();
        $this->expectException(BadMethodCallException::class);
        $model->setOldStatus('NOT_A_STATUS');
    }


    #[Test]
    public function getUpdatedValuesThrowsLogicExceptionWhenNoOldWCount(): void
    {
        $model = new CounterModel();
        $model->setOldStatus(TranslationStatus::STATUS_NEW);
        $model->setNewStatus(TranslationStatus::STATUS_TRANSLATED);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('old word count is not defined');
        $model->getUpdatedValues(10.0, 12);
    }

    #[Test]
    public function getUpdatedValuesWithDifferentStatusesAppliesDifferential(): void
    {
        $model = new CounterModel($this->makeOldWCount());
        $model->setOldStatus(TranslationStatus::STATUS_NEW);
        $model->setNewStatus(TranslationStatus::STATUS_TRANSLATED);

        $result = $model->getUpdatedValues(8.5, 10);

        // Weighted: old status (NEW) gets -8.5, new status (TRANSLATED) gets +8.5
        $this->assertEquals(-8.5, $result->getNewWords());
        $this->assertEquals(8.5, $result->getTranslatedWords());

        // Raw: old status (NEW) gets -10, new status (TRANSLATED) gets +10
        $this->assertEquals(-10, $result->getNewRawWords());
        $this->assertEquals(10, $result->getTranslatedRawWords());

        // Metadata copied from oldWCount
        $this->assertEquals(42, $result->getIdJob());
        $this->assertEquals('abc123', $result->getJobPassword());
        $this->assertEquals(100, $result->getIdSegment());
        $this->assertEquals(TranslationStatus::STATUS_NEW, $result->getOldStatus());
        $this->assertEquals(TranslationStatus::STATUS_TRANSLATED, $result->getNewStatus());
    }

    #[Test]
    public function getUpdatedValuesWithEquivalentStatusesReturnsZeroDiff(): void
    {
        $model = new CounterModel($this->makeOldWCount());
        $model->setOldStatus(TranslationStatus::STATUS_TRANSLATED);
        $model->setNewStatus(TranslationStatus::STATUS_TRANSLATED);

        $result = $model->getUpdatedValues(10.0, 12);

        // No differential applied because statuses are equivalent
        $this->assertEquals(0.0, $result->getNewWords());
        $this->assertEquals(0.0, $result->getDraftWords());
        $this->assertEquals(0.0, $result->getTranslatedWords());
        $this->assertEquals(0.0, $result->getApprovedWords());
        $this->assertEquals(0.0, $result->getRejectedWords());
    }

    #[Test]
    public function getUpdatedValuesWithZeroRawSkipsRawDifferential(): void
    {
        $model = new CounterModel($this->makeOldWCount());
        $model->setOldStatus(TranslationStatus::STATUS_DRAFT);
        $model->setNewStatus(TranslationStatus::STATUS_APPROVED);

        $result = $model->getUpdatedValues(5.0, 0);

        // Weighted applied
        $this->assertEquals(-5.0, $result->getDraftWords());
        $this->assertEquals(5.0, $result->getApprovedWords());

        // Raw NOT applied (raw_words_amount is 0 → empty())
        $this->assertEquals(0.0, $result->getDraftRawWords());
        $this->assertEquals(0.0, $result->getApprovedRawWords());
    }


    #[Test]
    public function setUpdatedValuesAccumulatesInValuesArray(): void
    {
        $model = new CounterModel($this->makeOldWCount());
        $model->setOldStatus(TranslationStatus::STATUS_NEW);
        $model->setNewStatus(TranslationStatus::STATUS_DRAFT);

        $model->setUpdatedValues(3.0, 4);
        $model->setUpdatedValues(7.0, 8);

        $values = $model->getValues();
        $this->assertCount(2, $values);
        $this->assertInstanceOf(WordCountStruct::class, $values[0]);
        $this->assertInstanceOf(WordCountStruct::class, $values[1]);
    }


    #[Test]
    public function sumDifferentialsThrowsLogicExceptionWhenNoOldWCount(): void
    {
        $model = new CounterModel();
        $model->setOldStatus(TranslationStatus::STATUS_NEW);
        $model->setNewStatus(TranslationStatus::STATUS_TRANSLATED);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('old word count is not defined');
        $model->sumDifferentials([]);
    }

    #[Test]
    public function sumDifferentialsSumsMultipleStructs(): void
    {
        $model = new CounterModel($this->makeOldWCount());
        $model->setOldStatus(TranslationStatus::STATUS_NEW);
        $model->setNewStatus(TranslationStatus::STATUS_TRANSLATED);

        // Create two differential structs
        $diff1 = new WordCountStruct();
        $diff1->setNewWords(-5.0);
        $diff1->setTranslatedWords(5.0);
        $diff1->setNewRawWords(-6);
        $diff1->setTranslatedRawWords(6);

        $diff2 = new WordCountStruct();
        $diff2->setNewWords(-3.0);
        $diff2->setTranslatedWords(3.0);
        $diff2->setNewRawWords(-4);
        $diff2->setTranslatedRawWords(4);

        $result = $model->sumDifferentials([$diff1, $diff2]);

        $this->assertEquals(-8.0, $result->getNewWords());
        $this->assertEquals(8.0, $result->getTranslatedWords());
        $this->assertEquals(-10, $result->getNewRawWords());
        $this->assertEquals(10, $result->getTranslatedRawWords());

        // Metadata from oldWCount
        $this->assertEquals(42, $result->getIdJob());
        $this->assertEquals('abc123', $result->getJobPassword());
        $this->assertEquals(100, $result->getIdSegment());
    }

    #[Test]
    public function sumDifferentialsWithEmptyArrayReturnsZeroStruct(): void
    {
        $model = new CounterModel($this->makeOldWCount());
        $model->setOldStatus(TranslationStatus::STATUS_DRAFT);
        $model->setNewStatus(TranslationStatus::STATUS_APPROVED);

        $result = $model->sumDifferentials([]);

        $this->assertEquals(0.0, $result->getNewWords());
        $this->assertEquals(0.0, $result->getDraftWords());
        $this->assertEquals(0.0, $result->getTranslatedWords());
        $this->assertEquals(0.0, $result->getApprovedWords());
    }


    #[Test]
    public function updateDBThrowsLogicExceptionWhenNoOldWCount(): void
    {
        $model = new CounterModel();
        $model->setOldStatus(TranslationStatus::STATUS_NEW);
        $model->setNewStatus(TranslationStatus::STATUS_TRANSLATED);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('old word count is not defined');
        $model->updateDB([]);
    }

    #[Test]
    public function updateDBThrowsExceptionOnFailedUpdate(): void
    {
        $oldWCount = $this->makeOldWCount();

        $daoMock = $this->createStub(WordCounterDao::class);
        $daoMock->method('updateWordCount')->willReturn(0);

        $model = new CounterModel($oldWCount, $daoMock);
        $model->setOldStatus(TranslationStatus::STATUS_NEW);
        $model->setNewStatus(TranslationStatus::STATUS_TRANSLATED);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to update counter');

        $diff = new WordCountStruct();
        $diff->setNewWords(-5.0);
        $diff->setTranslatedWords(5.0);
        $diff->setIdJob(42);
        $diff->setJobPassword('abc123');

        $model->updateDB([$diff]);
    }

    #[Test]
    public function updateDBReturnsNewTotalOnSuccess(): void
    {
        $oldWCount = $this->makeOldWCount();

        $daoMock = $this->createStub(WordCounterDao::class);
        $daoMock->method('updateWordCount')->willReturn(1);

        $model = new CounterModel($oldWCount, $daoMock);
        $model->setOldStatus(TranslationStatus::STATUS_NEW);
        $model->setNewStatus(TranslationStatus::STATUS_TRANSLATED);

        $diff = new WordCountStruct();
        $diff->setNewWords(-5.0);
        $diff->setTranslatedWords(5.0);
        $diff->setNewRawWords(-6);
        $diff->setTranslatedRawWords(6);

        $result = $model->updateDB([$diff]);

        $this->assertEquals(10.0 + (-5.0), $result->getNewWords());
        $this->assertEquals(20.0 + 5.0, $result->getTranslatedWords());
        $this->assertEquals(12 + (-6), $result->getNewRawWords());
        $this->assertEquals(25 + 6, $result->getTranslatedRawWords());

        $this->assertEquals(42, $result->getIdJob());
        $this->assertEquals('abc123', $result->getJobPassword());
        $this->assertEquals(100, $result->getIdSegment());
        $this->assertEquals(TranslationStatus::STATUS_NEW, $result->getOldStatus());
        $this->assertEquals(TranslationStatus::STATUS_TRANSLATED, $result->getNewStatus());
    }


    #[Test]
    public function initializeJobWordCountBuildsStructFromDaoResults(): void
    {
        $daoMock = $this->createStub(WordCounterDao::class);
        $daoMock->method('getStatsForJob')->willReturn([
            [
                'id' => 99,
                'TOTAL' => 55.0,
                'NEW' => 10.0,
                'DRAFT' => 5.0,
                'TRANSLATED' => 20.0,
                'APPROVED' => 12.0,
                'APPROVED2' => 6.0,
                'REJECTED' => 2.0,
                'TOTAL_RAW' => 80,
                'NEW_RAW' => 15,
                'DRAFT_RAW' => 8,
                'TRANSLATED_RAW' => 30,
                'APPROVED_RAW' => 16,
                'APPROVED2_RAW' => 7,
                'REJECTED_RAW' => 4,
            ],
        ]);
        $daoMock->method('initializeWordCount')->willReturn(1);

        $model = new CounterModel(null, $daoMock);
        $result = $model->initializeJobWordCount(99, 'pass123');

        $this->assertInstanceOf(WordCountStruct::class, $result);
        $this->assertEquals(99, $result->getIdJob());
        $this->assertEquals('pass123', $result->getJobPassword());
        $this->assertEquals(10.0, $result->getNewWords());
        $this->assertEquals(5.0, $result->getDraftWords());
        $this->assertEquals(20.0, $result->getTranslatedWords());
        $this->assertEquals(12.0, $result->getApprovedWords());
        $this->assertEquals(6.0, $result->getApproved2Words());
        $this->assertEquals(2.0, $result->getRejectedWords());
        $this->assertEquals(15, $result->getNewRawWords());
        $this->assertEquals(8, $result->getDraftRawWords());
        $this->assertEquals(30, $result->getTranslatedRawWords());
        $this->assertEquals(16, $result->getApprovedRawWords());
        $this->assertEquals(7, $result->getApproved2RawWords());
        $this->assertEquals(4, $result->getRejectedRawWords());
    }

    #[Test]
    public function initializeJobWordCountMethodParamOverridesConstructorDao(): void
    {
        $constructorDao = $this->createStub(WordCounterDao::class);
        $constructorDao->method('getStatsForJob')->willReturn([]);

        $methodDao = $this->createStub(WordCounterDao::class);
        $methodDao->method('getStatsForJob')->willReturn([
            [
                'id' => 77,
                'NEW' => 5.0,
                'DRAFT' => 0.0,
                'TRANSLATED' => 0.0,
                'APPROVED' => 0.0,
                'APPROVED2' => 0.0,
                'REJECTED' => 0.0,
                'NEW_RAW' => 8,
                'DRAFT_RAW' => 0,
                'TRANSLATED_RAW' => 0,
                'APPROVED_RAW' => 0,
                'APPROVED2_RAW' => 0,
                'REJECTED_RAW' => 0,
            ],
        ]);
        $methodDao->method('initializeWordCount')->willReturn(1);

        $model = new CounterModel(null, $constructorDao);
        $result = $model->initializeJobWordCount(77, 'pw', $methodDao);

        $this->assertEquals(77, $result->getIdJob());
        $this->assertEquals(5.0, $result->getNewWords());
    }

}
