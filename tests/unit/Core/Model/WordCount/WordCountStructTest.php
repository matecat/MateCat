<?php

namespace Matecat\Core\Model\WordCount;

use Matecat\TestHelpers\AbstractTest;
use Model\Jobs\JobStruct;
use Model\WordCount\WordCountStruct;
use PHPUnit\Framework\Attributes\Test;

class WordCountStructTest extends AbstractTest
{
    #[Test]
    public function loadFromJobSetsAllFields(): void
    {
        $job = new JobStruct();
        $job->id = 1;
        $job->password = 'abc';
        $job->new_words = 10.0;
        $job->draft_words = 20.0;
        $job->translated_words = 30.0;
        $job->approved_words = 40.0;
        $job->rejected_words = 5.0;
        $job->approved2_words = 15.0;
        $job->new_raw_words = 100.0;
        $job->draft_raw_words = 200.0;
        $job->translated_raw_words = 300.0;
        $job->approved_raw_words = 400.0;
        $job->approved2_raw_words = 150.0;
        $job->rejected_raw_words = 50.0;

        $struct = WordCountStruct::loadFromJob($job);

        $this->assertSame(1, $struct->getIdJob());
        $this->assertSame('abc', $struct->getJobPassword());
        $this->assertSame(10.0, $struct->getNewWords());
        $this->assertSame(20.0, $struct->getDraftWords());
        $this->assertSame(30.0, $struct->getTranslatedWords());
        $this->assertSame(40.0, $struct->getApprovedWords());
        $this->assertSame(5.0, $struct->getRejectedWords());
        $this->assertSame(15.0, $struct->getApproved2Words());
    }

    #[Test]
    public function loadFromJobWithNullPassword(): void
    {
        $job = new JobStruct();
        $job->id = 1;
        $job->password = null;
        $job->new_words = 0;
        $job->draft_words = 0;
        $job->translated_words = 0;
        $job->approved_words = 0;
        $job->rejected_words = 0;
        $job->approved2_words = 0;
        $job->new_raw_words = 0;
        $job->draft_raw_words = 0;
        $job->translated_raw_words = 0;
        $job->approved_raw_words = 0;
        $job->approved2_raw_words = 0;
        $job->rejected_raw_words = 0;

        $struct = WordCountStruct::loadFromJob($job);

        $this->assertNull($struct->getJobPassword());
    }

    #[Test]
    public function getTotalSumsEquivalentWords(): void
    {
        $struct = new WordCountStruct();
        $struct->setIdJob(1)->setJobPassword('abc');
        $struct->setNewWords(10)->setDraftWords(20)->setTranslatedWords(30);
        $struct->setApprovedWords(40)->setRejectedWords(5)->setApproved2Words(15);

        $this->assertSame(120.0, $struct->getTotal());
    }

    #[Test]
    public function getRawTotalSumsRawWords(): void
    {
        $struct = new WordCountStruct();
        $struct->setIdJob(1)->setJobPassword('abc');
        $struct->setNewRawWords(100)->setDraftRawWords(200)->setTranslatedRawWords(300);
        $struct->setApprovedRawWords(400)->setRejectedRawWords(50)->setApproved2RawWords(150);

        $this->assertSame(1200.0, $struct->getRawTotal());
    }

    #[Test]
    public function jsonSerializeReturnsExpectedStructure(): void
    {
        $struct = new WordCountStruct();
        $struct->setIdJob(1)->setJobPassword('abc');
        $struct->setNewWords(10)->setDraftWords(20)->setTranslatedWords(30);
        $struct->setApprovedWords(40)->setApproved2Words(15);

        $json = $struct->jsonSerialize();

        $this->assertArrayHasKey('equivalent', $json);
        $this->assertArrayHasKey('raw', $json);
        $this->assertArrayHasKey('new', $json['equivalent']);
        $this->assertArrayHasKey('total', $json['equivalent']);
    }

    #[Test]
    public function toArrayMatchesJsonSerialize(): void
    {
        $struct = new WordCountStruct();
        $struct->setIdJob(1)->setJobPassword('abc');
        $struct->setNewWords(10)->setDraftWords(20);

        $this->assertSame($struct->jsonSerialize(), $struct->toArray());
    }

    #[Test]
    public function settersReturnSelfForChaining(): void
    {
        $struct = new WordCountStruct();

        $this->assertSame($struct, $struct->setIdJob(1));
        $this->assertSame($struct, $struct->setJobPassword('abc'));
        $this->assertSame($struct, $struct->setNewWords(0));
        $this->assertSame($struct, $struct->setIdSegment(100));
        $this->assertSame($struct, $struct->setOldStatus('NEW'));
        $this->assertSame($struct, $struct->setNewStatus('TRANSLATED'));
    }

    #[Test]
    public function segmentAndStatusAccessors(): void
    {
        $struct = new WordCountStruct();
        $struct->setIdSegment(42)->setOldStatus('NEW')->setNewStatus('TRANSLATED');

        $this->assertSame(42, $struct->getIdSegment());
        $this->assertSame('NEW', $struct->getOldStatus());
        $this->assertSame('TRANSLATED', $struct->getNewStatus());
    }
}
