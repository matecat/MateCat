<?php

namespace Matecat\Core\Model\Translations;

use Matecat\TestHelpers\AbstractTest;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Translations\SegmentTranslationStruct;
use PHPUnit\Framework\Attributes\Test;

class SegmentTranslationStructTest extends AbstractTest
{
    #[Test]
    public function isReviewedStatusReturnsTrueForApproved(): void
    {
        $struct = new SegmentTranslationStruct();
        $struct->status = 'APPROVED';

        $this->assertTrue($struct->isReviewedStatus());
    }

    #[Test]
    public function isReviewedStatusReturnsTrueForApproved2(): void
    {
        $struct = new SegmentTranslationStruct();
        $struct->status = 'APPROVED2';

        $this->assertTrue($struct->isReviewedStatus());
    }

    #[Test]
    public function isReviewedStatusReturnsFalseForTranslated(): void
    {
        $struct = new SegmentTranslationStruct();
        $struct->status = 'TRANSLATED';

        $this->assertFalse($struct->isReviewedStatus());
    }

    #[Test]
    public function isTranslationStatusReturnsTrueForTranslated(): void
    {
        $struct = new SegmentTranslationStruct();
        $struct->status = 'TRANSLATED';

        $this->assertTrue($struct->isTranslationStatus());
    }

    #[Test]
    public function isTranslationStatusReturnsFalseForApproved(): void
    {
        $struct = new SegmentTranslationStruct();
        $struct->status = 'APPROVED';

        $this->assertFalse($struct->isTranslationStatus());
    }

    #[Test]
    public function isICEReturnsTrueForLockedIce(): void
    {
        $struct = new SegmentTranslationStruct();
        $struct->match_type = 'ICE';
        $struct->locked = true;

        $this->assertTrue($struct->isICE());
    }

    #[Test]
    public function isICEReturnsFalseForUnlockedIce(): void
    {
        $struct = new SegmentTranslationStruct();
        $struct->match_type = 'ICE';
        $struct->locked = false;

        $this->assertFalse($struct->isICE());
    }

    #[Test]
    public function isICEReturnsFalseForNonIce(): void
    {
        $struct = new SegmentTranslationStruct();
        $struct->match_type = '100%';
        $struct->locked = true;

        $this->assertFalse($struct->isICE());
    }

    #[Test]
    public function isPreTranslatedReturnsTrueForSkipped(): void
    {
        $struct = new SegmentTranslationStruct();
        $struct->tm_analysis_status = 'SKIPPED';

        $this->assertTrue($struct->isPreTranslated());
    }

    #[Test]
    public function isPreTranslatedReturnsFalseForDone(): void
    {
        $struct = new SegmentTranslationStruct();
        $struct->tm_analysis_status = 'DONE';

        $this->assertFalse($struct->isPreTranslated());
    }

    #[Test]
    public function arrayAccessWorks(): void
    {
        $struct = new SegmentTranslationStruct();
        $struct['id_segment'] = 100;
        $struct['status'] = 'TRANSLATED';

        $this->assertSame(100, $struct['id_segment']);
        $this->assertSame('TRANSLATED', $struct['status']);
        $this->assertTrue(isset($struct['status']));
    }

    // ─── getJob() / getChunk() ───

    #[Test]
    public function getJobReturnsJobStruct(): void
    {
        $job = new JobStruct();
        $job->id = 5;

        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getNotDeletedById')->willReturn([$job]);

        $struct = new SegmentTranslationStruct();
        $struct->id_job = 5;

        $result = $struct->getJob($jobDao);

        $this->assertInstanceOf(JobStruct::class, $result);
        $this->assertSame(5, $result->id);
    }

    #[Test]
    public function getJobReturnsNullWhenNotFound(): void
    {
        $jobDao = $this->createStub(JobDao::class);
        $jobDao->method('getNotDeletedById')->willReturn([]);

        $struct = new SegmentTranslationStruct();
        $struct->id_job = 999;

        $this->assertNull($struct->getJob($jobDao));
    }

    // ─── defaults ───

    #[Test]
    public function defaultValues(): void
    {
        $struct = new SegmentTranslationStruct();

        $this->assertSame(0, $struct->time_to_edit);
        $this->assertSame(0, $struct->mt_qe);
        $this->assertFalse($struct->warning);
        $this->assertNull($struct->autopropagated_from);
        $this->assertNull($struct->translation);
    }
}
