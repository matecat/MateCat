<?php

declare(strict_types=1);

namespace Matecat\Core\Model\ActivityLog;

use Matecat\TestHelpers\AbstractTest;
use Model\ActivityLog\ActivityLogStruct;
use PHPUnit\Framework\Attributes\Test;

class ActivityLogStructTest extends AbstractTest
{
    #[Test]
    public function getAction_returns_correct_string_for_download(): void
    {
        $this->assertSame('Editing Log downloaded', ActivityLogStruct::getAction(ActivityLogStruct::DOWNLOAD_EDIT_LOG));
    }

    #[Test]
    public function getAction_returns_correct_string_for_access(): void
    {
        $this->assertSame('Access to the Translate page', ActivityLogStruct::getAction(ActivityLogStruct::ACCESS_TRANSLATE_PAGE));
    }

    #[Test]
    public function getAction_returns_correct_string_for_project_created(): void
    {
        $this->assertSame('Project created.', ActivityLogStruct::getAction(ActivityLogStruct::PROJECT_CREATED));
    }

    #[Test]
    public function getAction_returns_correct_string_for_translation_delivered(): void
    {
        $this->assertSame('Translation Delivered', ActivityLogStruct::getAction(ActivityLogStruct::TRANSLATION_DELIVERED));
    }

    #[Test]
    public function struct_properties_have_correct_defaults(): void
    {
        $struct = new ActivityLogStruct();
        $this->assertNull($struct->id_project);
        $this->assertNull($struct->ID);
        $this->assertNull($struct->id_job);
        $this->assertNull($struct->uid);
        $this->assertNull($struct->ip);
        $this->assertNull($struct->memory_key);
        $this->assertSame('', $struct->email);
        $this->assertSame('', $struct->first_name);
        $this->assertSame('', $struct->last_name);
    }

    #[Test]
    public function constants_have_expected_values(): void
    {
        $this->assertSame(1, ActivityLogStruct::DOWNLOAD_EDIT_LOG);
        $this->assertSame(6, ActivityLogStruct::DOWNLOAD_TRANSLATION);
        $this->assertSame(12, ActivityLogStruct::ACCESS_ANALYZE_PAGE);
        $this->assertSame(18, ActivityLogStruct::PROJECT_CREATED);
        $this->assertSame(19, ActivityLogStruct::JOB_UNARCHIVED);
        $this->assertSame(101, ActivityLogStruct::TRANSLATION_DELIVERED);
    }
}
