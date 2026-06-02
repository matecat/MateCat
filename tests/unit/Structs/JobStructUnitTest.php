<?php

use Model\Jobs\JobStruct;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

#[Group('unit')]
class JobStructUnitTest extends AbstractTest
{
    private function createStruct(): JobStruct
    {
        return new JobStruct([
            'id' => null,
            'password' => "testpwd",
            'id_project' => "123",
            'job_first_segment' => "1",
            'job_last_segment' => "100",
            'source' => "en-US",
            'target' => "it-IT",
            'tm_keys' => '[]',
            'id_translator' => "",
            'job_type' => null,
            'total_time_to_edit' => "0",
            'avg_post_editing_effort' => "0",
            'last_opened_segment' => null,
            'id_tms' => "1",
            'id_mt_engine' => "1",
            'create_date' => "2024-01-01 00:00:00",
            'last_update' => "2024-01-01 00:00:00",
            'disabled' => "0",
            'owner' => "test@example.com",
            'status_owner' => "active",
            'status' => "active",
            'status_translator' => null,
            'completed' => false,
            'new_words' => "0",
            'draft_words' => "0",
            'translated_words' => "0",
            'approved_words' => "0",
            'rejected_words' => "0",
            'subject' => "test",
            'payable_rates' => '{}',
            'total_raw_wc' => "1",
            'new_raw_words' => 0,
            'draft_raw_words' => 0,
            'translated_raw_words' => 0,
            'approved_raw_words' => 0,
            'approved2_raw_words' => 0,
            'rejected_raw_words' => 0,
        ]);
    }

    #[Test]
    public function setIsReview_true_sets_review_flag()
    {
        $struct = $this->createStruct();

        $result = $struct->setIsReview(true);

        $this->assertTrue($struct->getIsReview());
        $this->assertSame($struct, $result);
    }

    #[Test]
    public function setIsReview_false_clears_review_flag()
    {
        $struct = $this->createStruct();
        $struct->setIsReview(true);

        $struct->setIsReview(false);

        $this->assertFalse($struct->getIsReview());
    }

    #[Test]
    public function isReview_defaults_to_false()
    {
        $struct = $this->createStruct();

        $this->assertFalse($struct->getIsReview());
    }

    #[Test]
    public function isSecondPassReview_true_when_review_and_sourcePage_3()
    {
        $struct = $this->createStruct();
        $struct->setIsReview(true);
        $struct->setSourcePage(3);

        $this->assertTrue($struct->isSecondPassReview());
    }
}