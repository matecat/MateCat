<?php

namespace unit\Plugins\Features\SegmentFilter;

use Model\DataAccess\ShapelessConcreteStruct;
use Model\Jobs\JobStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;
use Plugins\Features\SegmentFilter\Model\FilterDefinition;
use Plugins\Features\SegmentFilter\Model\SegmentFilterDao;
use Plugins\Features\SegmentFilter\Model\SegmentFilterModel;

class SegmentFilterModelTest extends AbstractTest
{
    private JobStruct $chunk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chunk = new JobStruct();
        $this->chunk->id = 1;
        $this->chunk->password = 'test';
        $this->chunk->job_first_segment = 1;
        $this->chunk->job_last_segment = 100;
    }

    #[Test]
    public function getSegmentListCallsFindSegmentIdsBySimpleFilterWhenNotSampled(): void
    {
        $filter = new FilterDefinition(['status' => 'TRANSLATED']);

        $expected = [new ShapelessConcreteStruct()];

        $dao = $this->createMock(SegmentFilterDao::class);
        $dao->expects($this->once())
            ->method('findSegmentIdsBySimpleFilter')
            ->with($this->chunk, $filter)
            ->willReturn($expected);
        $dao->expects($this->never())
            ->method('findSegmentIdsForSample');

        $model = new SegmentFilterModel($this->chunk, $filter, $dao);
        $result = $model->getSegmentList();

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function getSegmentListCallsFindSegmentIdsForSampleWhenSampled(): void
    {
        $filter = new FilterDefinition([
            'status' => '',
            'sample' => ['type' => 'mt', 'size' => 0]
        ]);

        $expected = [new ShapelessConcreteStruct(), new ShapelessConcreteStruct()];

        $dao = $this->createMock(SegmentFilterDao::class);
        $dao->expects($this->once())
            ->method('findSegmentIdsForSample')
            ->with($this->chunk, $filter)
            ->willReturn($expected);
        $dao->expects($this->never())
            ->method('findSegmentIdsBySimpleFilter');

        $model = new SegmentFilterModel($this->chunk, $filter, $dao);
        $result = $model->getSegmentList();

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function constructorDefaultsDaoWhenNotProvided(): void
    {
        $filter = new FilterDefinition(['status' => 'NEW']);
        $model = new SegmentFilterModel($this->chunk, $filter);

        $reflection = new \ReflectionClass($model);
        $prop = $reflection->getProperty('segmentFilterDao');

        $this->assertInstanceOf(SegmentFilterDao::class, $prop->getValue($model));
    }
}
