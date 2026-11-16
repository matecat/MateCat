<?php

namespace Matecat\Core\Model\MTQE;

use Matecat\TestHelpers\AbstractTest;
use Model\MTQE\Templates\DTO\MTQEWorkflowParams;
use PHPUnit\Framework\Attributes\Test;
use Utils\TaskRunner\Commons\Params;
use Utils\TaskRunner\Commons\QueueElement;

class MTQEWorkflowParamsTest extends AbstractTest
{
    public function testDefaults(): void
    {
        $p = new MTQEWorkflowParams();

        $this->assertFalse($p->analysis_ignore_100);
        $this->assertFalse($p->analysis_ignore_101);
        $this->assertTrue($p->confirm_best_quality_mt);
        $this->assertFalse($p->lock_best_quality_mt);
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

    #[Test]
    public function fromQueueValue_returns_the_same_instance(): void
    {
        $p = new MTQEWorkflowParams(['lock_best_quality_mt' => true]);

        $this->assertSame($p, MTQEWorkflowParams::fromQueueValue($p));
    }

    #[Test]
    public function fromQueueValue_hydrates_a_params_value(): void
    {
        $p = MTQEWorkflowParams::fromQueueValue(new Params(['lock_best_quality_mt' => true, 'qe_model_version' => 2]));

        $this->assertTrue($p->lock_best_quality_mt);
        $this->assertSame(2, $p->qe_model_version);
        $this->assertTrue($p->confirm_best_quality_mt);
    }

    #[Test]
    public function fromQueueValue_returns_the_defaults_for_null(): void
    {
        $this->assertEquals(new MTQEWorkflowParams(), MTQEWorkflowParams::fromQueueValue(null));
    }

    #[Test]
    public function fromQueueValue_reads_the_nested_params_of_a_queue_element_after_the_broker_round_trip(): void
    {
        $element = new QueueElement();
        $element->classLoad = 'TMAnalysisWorker';
        $element->params = new Params([
            'mt_qe_workflow_parameters' => new MTQEWorkflowParams(['lock_best_quality_mt' => true, 'analysis_ignore_101' => true]),
        ]);

        // the same decode the Executor applies to a frame body
        $decoded = new QueueElement(json_decode((string)json_encode($element), true));
        $this->assertInstanceOf(Params::class, $decoded->params->mt_qe_workflow_parameters);

        $p = MTQEWorkflowParams::fromQueueValue($decoded->params->mt_qe_workflow_parameters);

        $this->assertTrue($p->lock_best_quality_mt);
        $this->assertTrue($p->analysis_ignore_101);
        $this->assertFalse($p->analysis_ignore_100);
    }
}
