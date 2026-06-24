<?php

namespace Matecat\Core\View\API\V2\Json;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
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

    /**
     * @throws Exception
     */
    public function testRenderWithIssuesAttachesIssuesToVersion(): void
    {
        $records = [
            $this->makeRecord([
                'id' => 1, 'id_segment' => 10, 'version_number' => 1,
                'raw_diff' => null,
                'qa_id_segment' => 10, 'qa_uid' => 1, 'qa_id' => 100,
                'qa_id_job' => 1, 'qa_id_category' => 5, 'qa_severity' => 'minor',
                'qa_translation_version' => 1, 'qa_start_node' => 0, 'qa_start_offset' => 0,
                'qa_end_node' => 0, 'qa_end_offset' => 5, 'qa_is_full_segment' => 0,
                'qa_penalty_points' => 1.0, 'qa_comment' => 'typo',
                'qa_create_date' => '2024-01-01 00:00:00', 'qa_target_text' => 'test',
                'qa_source_page' => 1,
            ]),
            $this->makeRecord([
                'id' => 2, 'id_segment' => 10, 'version_number' => 2,
                'raw_diff' => '{"1":"added"}',
                'qa_id_segment' => null,
            ]),
        ];

        $view   = new SegmentVersion($this->makeJobStruct(), $records, true, null, $this->makeMetadataDao());
        $result = $view->render();

        $this->assertCount(2, $result);
        $this->assertNotEmpty($result[0]['issues']);
        $this->assertArrayHasKey('id', $result[0]['issues'][0]);
        $this->assertSame([], $result[1]['issues']);
    }

    /**
     * @throws Exception
     */
    public function testRenderWithIssuesMultipleIssuesSameVersion(): void
    {
        $base = [
            'id' => 1, 'id_segment' => 10, 'version_number' => 1,
            'raw_diff' => null,
            'qa_uid' => 1, 'qa_id_job' => 1, 'qa_id_category' => 5,
            'qa_severity' => 'minor', 'qa_translation_version' => 1,
            'qa_start_node' => 0, 'qa_start_offset' => 0,
            'qa_end_node' => 0, 'qa_end_offset' => 5, 'qa_is_full_segment' => 0,
            'qa_penalty_points' => 1.0, 'qa_comment' => 'issue',
            'qa_create_date' => '2024-01-01 00:00:00', 'qa_target_text' => 'text',
            'qa_source_page' => 1,
        ];

        $records = [
            $this->makeRecord(array_merge($base, ['qa_id' => 100, 'qa_id_segment' => 10])),
            $this->makeRecord(array_merge($base, ['qa_id' => 101, 'qa_id_segment' => 10])),
        ];

        $view   = new SegmentVersion($this->makeJobStruct(), $records, true, null, $this->makeMetadataDao());
        $result = $view->render();

        $this->assertCount(1, $result);
        $this->assertCount(2, $result[0]['issues']);
    }

    /**
     * @throws Exception
     */
    public function testRenderWithIssuesHandlesRawDiffJson(): void
    {
        $records = [
            $this->makeRecord([
                'id' => 1, 'id_segment' => 10, 'version_number' => 1,
                'raw_diff' => '{"0":"hello","1":"world"}',
                'qa_id_segment' => null,
            ]),
        ];

        $view   = new SegmentVersion($this->makeJobStruct(), $records, true, null, $this->makeMetadataDao());
        $result = $view->render();

        $this->assertCount(1, $result);
        $this->assertSame(['hello', 'world'], $result[0]['diff']);
    }

    /**
     * @throws Exception
     */
    public function testRenderWithIssuesHandlesNullRawDiff(): void
    {
        $records = [
            $this->makeRecord([
                'id' => 1, 'id_segment' => 10, 'version_number' => 1,
                'raw_diff' => null,
                'qa_id_segment' => null,
            ]),
        ];

        $view   = new SegmentVersion($this->makeJobStruct(), $records, true, null, $this->makeMetadataDao());
        $result = $view->render();

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['diff']);
    }
}
