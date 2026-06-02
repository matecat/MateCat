<?php

namespace unit\View\API\V2\Json;

use Exception;
use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use TestHelpers\AbstractTest;
use View\API\V2\Json\SegmentVersion;

#[CoversClass(SegmentVersion::class)]
class SegmentVersionTest extends AbstractTest
{
    private function makeJobStruct(int $id = 1, string $password = 'abc123', string $source = 'en-US', string $target = 'it-IT'): JobStruct
    {
        $job           = new JobStruct();
        $job->id       = $id;
        $job->password = $password;
        $job->source   = $source;
        $job->target   = $target;

        return $job;
    }

    /** @return Stub&MetadataDao */
    private function makeMetadataDao(?array $handlers = []): Stub
    {
        $dao = $this->createStub(MetadataDao::class);
        $dao->method('getSubfilteringCustomHandlers')->willReturn($handlers);

        return $dao;
    }

    private function makeRecord(array $fields = []): ShapelessConcreteStruct
    {
        $defaults = [
            'id'             => 1,
            'id_segment'     => 10,
            'id_job'         => 1,
            'translation'    => 'Hello world',
            'version_number' => 1,
            'propagated_from'=> 0,
            'creation_date'  => '2024-01-01 00:00:00',
        ];

        return new ShapelessConcreteStruct(array_merge($defaults, $fields));
    }

    /**
     * @throws Exception
     */
    public function testConstructorAcceptsEmptyData(): void
    {
        $view = new SegmentVersion($this->makeJobStruct(), []);
        $this->assertInstanceOf(SegmentVersion::class, $view);
    }

    /**
     * @throws Exception
     */
    public function testConstructorAcceptsExplicitFeatureSet(): void
    {
        $featureSet = new FeatureSet();
        $view       = new SegmentVersion($this->makeJobStruct(), [], false, $featureSet);
        $this->assertInstanceOf(SegmentVersion::class, $view);
    }

    /**
     * @throws Exception
     */
    public function testRenderNormalReturnsEmptyArrayForEmptyData(): void
    {
        $view   = new SegmentVersion($this->makeJobStruct(), [], false, null, $this->makeMetadataDao());
        $result = $view->render();

        $this->assertSame([], $result);
    }

    /**
     * @throws Exception
     */
    public function testRenderWithIssuesReturnsEmptyArrayForEmptyData(): void
    {
        $view   = new SegmentVersion($this->makeJobStruct(), [], true, null, $this->makeMetadataDao());
        $result = $view->render();

        $this->assertSame([], $result);
    }

    /**
     * @throws Exception
     */
    public function testRenderItemReturnsExpectedKeys(): void
    {
        $view   = new SegmentVersion($this->makeJobStruct(), [], false, null, $this->makeMetadataDao());
        $record = $this->makeRecord();
        $result = $view->renderItem($record);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('id_segment', $result);
        $this->assertArrayHasKey('id_job', $result);
        $this->assertArrayHasKey('translation', $result);
        $this->assertArrayHasKey('version_number', $result);
        $this->assertArrayHasKey('propagated_from', $result);
        $this->assertArrayHasKey('created_at', $result);
    }

    /**
     * @throws Exception
     */
    public function testRenderItemCastsIdToInt(): void
    {
        $view   = new SegmentVersion($this->makeJobStruct(), [], false, null, $this->makeMetadataDao());
        $record = $this->makeRecord(['id' => '42', 'id_segment' => '100', 'id_job' => '1']);
        $result = $view->renderItem($record);

        $this->assertSame(42, $result['id']);
        $this->assertSame(100, $result['id_segment']);
        $this->assertSame(1, $result['id_job']);
    }

    /**
     * @throws Exception
     */
    public function testRenderItemSetsCreationDate(): void
    {
        $view   = new SegmentVersion($this->makeJobStruct(), [], false, null, $this->makeMetadataDao());
        $record = $this->makeRecord(['creation_date' => '2024-06-01 12:00:00']);
        $result = $view->renderItem($record);

        $this->assertSame('2024-06-01 12:00:00', $result['created_at']);
    }

    /**
     * @throws Exception
     */
    public function testRenderNormalReturnsSingleRecord(): void
    {
        $record = $this->makeRecord();
        $view   = new SegmentVersion($this->makeJobStruct(), [$record], false, null, $this->makeMetadataDao());
        $result = $view->render();

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertSame(1, $result[0]['id']);
    }

    /**
     * @throws Exception
     */
    public function testRenderNormalReturnsMultipleRecords(): void
    {
        $records = [
            $this->makeRecord(['id' => 1, 'id_segment' => 10, 'version_number' => 1]),
            $this->makeRecord(['id' => 2, 'id_segment' => 10, 'version_number' => 2]),
        ];

        $view   = new SegmentVersion($this->makeJobStruct(), $records, false, null, $this->makeMetadataDao());
        $result = $view->render();

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame(2, $result[1]['id']);
    }

    /**
     * @throws Exception
     */
    public function testRenderWithIssuesGroupsVersions(): void
    {
        // Two records with same id → same version, different qa data
        $records = [
            $this->makeRecord(['id' => 1, 'id_segment' => 10, 'version_number' => 1, 'qa_id_segment' => null]),
            $this->makeRecord(['id' => 2, 'id_segment' => 10, 'version_number' => 2, 'qa_id_segment' => null]),
        ];

        $view   = new SegmentVersion($this->makeJobStruct(), $records, true, null, $this->makeMetadataDao());
        $result = $view->render();

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('issues', $result[0]);
        $this->assertArrayHasKey('issues', $result[1]);
        $this->assertSame([], $result[0]['issues']);
    }

    /**
     * @throws Exception
     */
    public function testRenderWithIssuesSingleVersionHasIssuesKey(): void
    {
        $record = $this->makeRecord(['id' => 1, 'qa_id_segment' => null]);
        $view   = new SegmentVersion($this->makeJobStruct(), [$record], true, null, $this->makeMetadataDao());
        $result = $view->render();

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('issues', $result[0]);
    }

    /**
     * @throws Exception
     */
    public function testRenderItemHandlesNullTranslation(): void
    {
        $view   = new SegmentVersion($this->makeJobStruct(), [], false, null, $this->makeMetadataDao());
        $record = $this->makeRecord(['translation' => null]);
        $result = $view->renderItem($record);

        $this->assertArrayHasKey('translation', $result);
        $this->assertIsString($result['translation']);
    }
}
