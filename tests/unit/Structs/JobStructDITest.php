<?php

use Model\Comments\CommentDao;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Outsource\ConfirmationDao;
use Model\Outsource\TranslatedConfirmationStruct;
use Model\Segments\SegmentDao;
use Model\TmKeyManagement\UserKeysModel;
use Model\Translations\WarningDao;
use Model\Translators\JobsTranslatorsDao;
use Model\Translators\JobsTranslatorsStruct;
use Model\Users\UserStruct;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Utils\Constants\JobStatus;

#[AllowMockObjectsWithoutExpectations]
class JobStructDITest extends AbstractTest
{
    private JobStruct $struct;

    public function setUp(): void
    {
        parent::setUp();

        $this->struct = new JobStruct([
            'id' => 42,
            'password' => 'secret',
            'id_project' => 99,
            'job_first_segment' => '1',
            'job_last_segment' => '100',
            'source' => 'en-US',
            'target' => 'it-IT',
            'tm_keys' => '[{"tm":true}]',
            'id_translator' => '',
            'job_type' => null,
            'total_time_to_edit' => '0',
            'avg_post_editing_effort' => '0',
            'last_opened_segment' => null,
            'id_tms' => '1',
            'id_mt_engine' => '1',
            'create_date' => '2024-01-01 00:00:00',
            'last_update' => '2024-01-01 00:00:00',
            'disabled' => '0',
            'owner' => 'test@example.com',
            'status_owner' => 'active',
            'status' => 'active',
            'status_translator' => null,
            'completed' => false,
            'new_words' => '0',
            'draft_words' => '0',
            'translated_words' => '0',
            'approved_words' => '0',
            'rejected_words' => '0',
            'subject' => 'test',
            'payable_rates' => '{}',
            'total_raw_wc' => 1,
        ]);
    }

    #[Test]
    public function getTranslator_returns_struct_from_dao()
    {
        $translatorStruct = new JobsTranslatorsStruct();
        $translatorStruct->source = 'en-US';

        $dao = $this->createMock(JobsTranslatorsDao::class);
        $dao->method('setCacheTTL')->willReturnSelf();
        $dao->method('findByJobsStruct')->willReturn([$translatorStruct]);

        $result = $this->struct->getTranslator($dao);

        $this->assertSame($translatorStruct, $result);
    }

    #[Test]
    public function getTranslator_returns_null_when_no_translator()
    {
        $dao = $this->createMock(JobsTranslatorsDao::class);
        $dao->method('setCacheTTL')->willReturnSelf();
        $dao->method('findByJobsStruct')->willReturn([]);

        $result = $this->struct->getTranslator($dao);

        $this->assertNull($result);
    }


    #[Test]
    public function getOutsource_returns_null_when_no_outsource()
    {
        $dao = $this->createMock(ConfirmationDao::class);
        $dao->method('setCacheTTL')->willReturnSelf();
        $dao->method('getConfirmation')->willReturn(null);

        $result = $this->struct->getOutsource($dao);

        $this->assertNull($result);
    }

    #[Test]
    public function getOutsource_returns_null_when_empty_vendor()
    {
        $outsource = $this->createMock(TranslatedConfirmationStruct::class);
        $outsource->id_vendor = 0; // empty() returns true for 0

        $dao = $this->createMock(ConfirmationDao::class);
        $dao->method('setCacheTTL')->willReturnSelf();
        $dao->method('getConfirmation')->willReturn($outsource);

        $result = $this->struct->getOutsource($dao);

        $this->assertNull($result);
    }

    #[Test]
    public function getOutsource_returns_outsource_with_valid_vendor()
    {
        $outsource = new TranslatedConfirmationStruct([
            'id_vendor' => TranslatedConfirmationStruct::VENDOR_ID,
            'id_job' => 42,
            'password' => 'secret',
        ]);

        $dao = $this->createMock(ConfirmationDao::class);
        $dao->method('setCacheTTL')->willReturnSelf();
        $dao->method('getConfirmation')->willReturn($outsource);

        $result = $this->struct->getOutsource($dao);

        $this->assertSame($outsource, $result);
    }

    #[Test]
    public function getOpenThreadsCount_returns_count_for_matching_job()
    {
        $thread = new \stdClass();
        $thread->id_job = 42;
        $thread->password = 'secret';
        $thread->count = 5;

        $dao = $this->createMock(CommentDao::class);
        $dao->method('setCacheTTL')->willReturnSelf();
        $dao->method('getOpenThreadsForProjects')->willReturn([$thread]);

        $result = $this->struct->getOpenThreadsCount($dao);

        $this->assertSame(5, $result);
    }

    #[Test]
    public function getOpenThreadsCount_returns_zero_for_non_matching_job()
    {
        $thread = new \stdClass();
        $thread->id_job = 999;
        $thread->password = 'other';
        $thread->count = 5;

        $dao = $this->createMock(CommentDao::class);
        $dao->method('setCacheTTL')->willReturnSelf();
        $dao->method('getOpenThreadsForProjects')->willReturn([$thread]);

        $result = $this->struct->getOpenThreadsCount($dao);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function getOpenThreadsCount_returns_zero_for_empty_threads()
    {
        $dao = $this->createMock(CommentDao::class);
        $dao->method('setCacheTTL')->willReturnSelf();
        $dao->method('getOpenThreadsForProjects')->willReturn([]);

        $result = $this->struct->getOpenThreadsCount($dao);

        $this->assertSame(0, $result);
    }


    #[Test]
    public function getWarningsCount_returns_count_for_matching_job()
    {
        $warning = new \stdClass();
        $warning->id_job = 42;
        $warning->password = 'secret';
        $warning->count = '3';
        $warning->segment_list = '10,20,30';

        $dao = $this->createMock(WarningDao::class);
        $dao->method('setCacheTTL')->willReturnSelf();
        $dao->method('getWarningsByProjectIds')->willReturn([$warning]);

        $result = $this->struct->getWarningsCount($dao);

        $this->assertSame(3, $result->warnings_count);
        $this->assertSame([10, 20, 30], $result->warning_segments);
    }

    #[Test]
    public function getWarningsCount_returns_zero_when_no_matching_job()
    {
        $warning = new \stdClass();
        $warning->id_job = 999;
        $warning->password = 'other';
        $warning->count = '3';
        $warning->segment_list = '1,2';

        $dao = $this->createMock(WarningDao::class);
        $dao->method('setCacheTTL')->willReturnSelf();
        $dao->method('getWarningsByProjectIds')->willReturn([$warning]);

        $result = $this->struct->getWarningsCount($dao);

        $this->assertSame(0, $result->warnings_count);
        $this->assertObjectNotHasProperty('warning_segments', $result);
    }

    #[Test]
    public function getWarningsCount_returns_zero_for_empty_warnings()
    {
        $dao = $this->createMock(WarningDao::class);
        $dao->method('setCacheTTL')->willReturnSelf();
        $dao->method('getWarningsByProjectIds')->willReturn([]);

        $result = $this->struct->getWarningsCount($dao);

        $this->assertSame(0, $result->warnings_count);
    }


    #[Test]
    public function getChunks_returns_chunks_from_dao()
    {
        $chunk = new JobStruct(['id' => 43, 'password' => 'chunk1', 'id_project' => 99]);

        $dao = $this->createMock(JobDao::class);
        $dao->method('getNotDeletedById')->willReturn([$chunk]);

        $result = $this->struct->getChunks($dao);

        $this->assertCount(1, $result);
        $this->assertSame($chunk, $result[0]);
    }

    #[Test]
    public function getChunks_throws_when_id_is_null()
    {
        $struct = new JobStruct(['id' => null, 'password' => 'x', 'id_project' => 1]);
        $dao = $this->createStub(JobDao::class);

        $this->expectException(\DomainException::class);
        $struct->getChunks($dao);
    }


    #[Test]
    public function getClientKeys_delegates_to_user_keys_model()
    {
        $user = new UserStruct(['uid' => 1, 'email' => 'test@example.com']);
        $expectedKeys = ['key1' => []];

        $model = $this->createMock(UserKeysModel::class);
        $model->method('getKeys')->willReturn($expectedKeys);

        $result = $this->struct->getClientKeys($user, 'translator', $model);

        $this->assertSame($expectedKeys, $result);
    }


    #[Test]
    public function getPeeForTranslatedSegments_returns_rounded_pee()
    {
        $stats = new ShapelessConcreteStruct(['avg_pee' => 7.567]);

        $dao = $this->createMock(JobDao::class);
        $dao->method('setCacheTTL')->willReturnSelf();
        $dao->method('getPeeStats')->willReturn($stats);

        $result = $this->struct->getPeeForTranslatedSegments($dao);

        $this->assertSame(7.57, $result);
    }

    #[Test]
    public function getPeeForTranslatedSegments_returns_null_when_pee_gte_100()
    {
        $stats = new ShapelessConcreteStruct(['avg_pee' => 100.0]);

        $dao = $this->createMock(JobDao::class);
        $dao->method('setCacheTTL')->willReturnSelf();
        $dao->method('getPeeStats')->willReturn($stats);

        $result = $this->struct->getPeeForTranslatedSegments($dao);

        $this->assertNull($result);
    }

    #[Test]
    public function getPeeForTranslatedSegments_returns_zero_when_avg_pee_null()
    {
        $stats = new ShapelessConcreteStruct(['avg_pee' => null]);

        $dao = $this->createMock(JobDao::class);
        $dao->method('setCacheTTL')->willReturnSelf();
        $dao->method('getPeeStats')->willReturn($stats);

        $result = $this->struct->getPeeForTranslatedSegments($dao);

        $this->assertSame(0.0, $result);
    }

    #[Test]
    public function getPeeForTranslatedSegments_throws_when_id_is_null()
    {
        $struct = new JobStruct(['id' => null, 'password' => 'x', 'id_project' => 1]);
        $dao = $this->createStub(JobDao::class);

        $this->expectException(\DomainException::class);
        $struct->getPeeForTranslatedSegments($dao);
    }

    #[Test]
    public function getPeeForTranslatedSegments_throws_when_password_is_null()
    {
        $struct = new JobStruct(['id' => 1, 'password' => null, 'id_project' => 1]);
        $dao = $this->createStub(JobDao::class);

        $this->expectException(\DomainException::class);
        $struct->getPeeForTranslatedSegments($dao);
    }


    #[Test]
    public function getSegments_returns_segments_from_dao()
    {
        $segments = [new \Model\Segments\SegmentStruct(['id' => 1])];

        $dao = $this->createMock(SegmentDao::class);
        $dao->method('getByChunkId')->willReturn($segments);

        $result = $this->struct->getSegments($dao);

        $this->assertSame($segments, $result);
    }

    #[Test]
    public function getSegments_throws_when_id_is_null()
    {
        $struct = new JobStruct(['id' => null, 'password' => 'x', 'id_project' => 1]);
        $dao = $this->createStub(SegmentDao::class);

        $this->expectException(\DomainException::class);
        $struct->getSegments($dao);
    }

    #[Test]
    public function getSegments_throws_when_password_is_null()
    {
        $struct = new JobStruct(['id' => 1, 'password' => null, 'id_project' => 1]);
        $dao = $this->createStub(SegmentDao::class);

        $this->expectException(\DomainException::class);
        $struct->getSegments($dao);
    }


    #[Test]
    public function getErrorsCount_delegates_to_warning_dao()
    {
        $dao = $this->createMock(WarningDao::class);
        $dao->method('getErrorsByChunk')->willReturn(7);

        $result = $this->struct->getErrorsCount($dao);

        $this->assertSame(7, $result);
    }

    #[Test]
    public function getErrorsCount_returns_zero()
    {
        $dao = $this->createMock(WarningDao::class);
        $dao->method('getErrorsByChunk')->willReturn(0);

        $result = $this->struct->getErrorsCount($dao);

        $this->assertSame(0, $result);
    }


    #[Test]
    public function isCanceled_true_when_status_owner_is_cancelled()
    {
        $struct = new JobStruct(['id' => 1, 'password' => 'x', 'id_project' => 1, 'status_owner' => JobStatus::STATUS_CANCELLED]);
        $this->assertTrue($struct->isCanceled());
    }

    #[Test]
    public function isCanceled_false_when_status_owner_is_active()
    {
        $struct = new JobStruct(['id' => 1, 'password' => 'x', 'id_project' => 1, 'status_owner' => JobStatus::STATUS_ACTIVE]);
        $this->assertFalse($struct->isCanceled());
    }

    #[Test]
    public function isArchived_true_when_status_owner_is_archived()
    {
        $struct = new JobStruct(['id' => 1, 'password' => 'x', 'id_project' => 1, 'status_owner' => JobStatus::STATUS_ARCHIVED]);
        $this->assertTrue($struct->isArchived());
    }

    #[Test]
    public function isArchived_false_when_status_owner_is_active()
    {
        $struct = new JobStruct(['id' => 1, 'password' => 'x', 'id_project' => 1, 'status_owner' => JobStatus::STATUS_ACTIVE]);
        $this->assertFalse($struct->isArchived());
    }

    #[Test]
    public function isDeleted_true_when_status_owner_is_deleted()
    {
        $struct = new JobStruct(['id' => 1, 'password' => 'x', 'id_project' => 1, 'status_owner' => JobStatus::STATUS_DELETED]);
        $this->assertTrue($struct->isDeleted());
    }

    #[Test]
    public function isDeleted_false_when_status_owner_is_active()
    {
        $struct = new JobStruct(['id' => 1, 'password' => 'x', 'id_project' => 1, 'status_owner' => JobStatus::STATUS_ACTIVE]);
        $this->assertFalse($struct->isDeleted());
    }
}