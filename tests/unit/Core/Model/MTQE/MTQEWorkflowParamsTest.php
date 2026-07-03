<?php

namespace Matecat\Core\Model\MTQE;

use Matecat\TestHelpers\AbstractTest;
use Model\MTQE\Templates\DTO\MTQEWorkflowParams;
use Utils\Constants\TranslationStatus;

class MTQEWorkflowParamsTest extends AbstractTest
{
    public function testDefaults(): void
    {
        $p = new MTQEWorkflowParams();

        $this->assertFalse($p->analysis_ignore_100);
        $this->assertFalse($p->analysis_ignore_101);
        $this->assertTrue($p->confirm_best_quality_mt);
        $this->assertFalse($p->lock_best_quality_mt);
        $this->assertSame(TranslationStatus::STATUS_APPROVED, $p->best_quality_mt_analysis_status);
        $this->assertSame(3, $p->qe_model_version);
    }

    public function testJsonSerialize(): void
    {
        $p = new MTQEWorkflowParams();
        $arr = $p->jsonSerialize();

        $this->assertIsArray($arr);
        $this->assertArrayHasKey('confirm_best_quality_mt', $arr);
    }

    public function testToString(): void
    {
        $p = new MTQEWorkflowParams();
        $str = (string)$p;

        $this->assertJson($str);
    }

    public function testHydrateFromConstructor(): void
    {
        $p = new MTQEWorkflowParams(['analysis_ignore_100' => true, 'qe_model_version' => 2]);

        $this->assertTrue($p->analysis_ignore_100);
        $this->assertSame(2, $p->qe_model_version);
    }
}
