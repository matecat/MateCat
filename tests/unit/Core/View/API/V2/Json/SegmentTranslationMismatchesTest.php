<?php

namespace Matecat\Core\View\API\V2\Json;

use Exception;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use RuntimeException;
use View\API\V2\Json\SegmentTranslationMismatches;

#[CoversClass(SegmentTranslationMismatches::class)]
class SegmentTranslationMismatchesTest extends AbstractTest
{
    private function featureSet(): FeatureSet
    {
        return new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class));
    }

    private function makeJobStruct(int $id = 1, string $password = 'abc123'): JobStruct
    {
        $job           = new JobStruct();
        $job->id       = $id;
        $job->password = $password;

        return $job;
    }

    /** @return Stub&MetadataDao */
    private function makeMetadataDao(?array $handlers = []): Stub
    {
        $dao = $this->createStub(MetadataDao::class);
        $dao->method('getSubfilteringCustomHandlers')->willReturn($handlers);

        return $dao;
    }

    /**
     * @throws Exception
     */
    public function testConstructorAcceptsEmptyData(): void
    {
        $view = new SegmentTranslationMismatches([], $this->makeJobStruct(), 0, $this->featureSet());
        $this->assertInstanceOf(SegmentTranslationMismatches::class, $view);
    }

    /**
     * @throws Exception
     */
    public function testRenderReturnsExpectedStructureForEmptyData(): void
    {
        $view   = new SegmentTranslationMismatches([], $this->makeJobStruct(), 0, $this->featureSet(), $this->makeMetadataDao());
        $result = $view->render();

        $this->assertArrayHasKey('editable', $result);
        $this->assertArrayHasKey('not_editable', $result);
        $this->assertArrayHasKey('prop_available', $result);
        $this->assertSame([], $result['editable']);
        $this->assertSame([], $result['not_editable']);
        $this->assertSame(0, $result['prop_available']);
    }

    /**
     * @throws Exception
     */
    public function testRenderPropagationsValue(): void
    {
        $view   = new SegmentTranslationMismatches([], $this->makeJobStruct(), 3, $this->featureSet(), $this->makeMetadataDao());
        $result = $view->render();

        $this->assertSame(3, $result['prop_available']);
    }

    /**
     * @throws Exception
     */
    public function testRenderEditableRow(): void
    {
        $data = [
            [
                'source'      => 'en-US',
                'target'      => 'it-IT',
                'editable'    => true,
                'translation' => 'Hello',
                'TOT'         => 5,
                'involved_id' => '1,2,3',
            ],
        ];

        $view   = new SegmentTranslationMismatches($data, $this->makeJobStruct(), 0, $this->featureSet(), $this->makeMetadataDao());
        $result = $view->render();

        $this->assertCount(1, $result['editable']);
        $this->assertCount(0, $result['not_editable']);
        $this->assertSame(5, $result['editable'][0]['TOT']);
        $this->assertSame(['1', '2', '3'], $result['editable'][0]['involved_id']);
        $this->assertArrayHasKey('translation', $result['editable'][0]);
    }

    /**
     * @throws Exception
     */
    public function testRenderNotEditableRow(): void
    {
        $data = [
            [
                'source'      => 'en-US',
                'target'      => 'it-IT',
                'editable'    => false,
                'translation' => 'World',
                'TOT'         => 2,
                'involved_id' => '4',
            ],
        ];

        $view   = new SegmentTranslationMismatches($data, $this->makeJobStruct(), 0, $this->featureSet(), $this->makeMetadataDao());
        $result = $view->render();

        $this->assertCount(0, $result['editable']);
        $this->assertCount(1, $result['not_editable']);
        $this->assertSame(2, $result['not_editable'][0]['TOT']);
        $this->assertSame(['4'], $result['not_editable'][0]['involved_id']);
    }

    /**
     * @throws Exception
     */
    public function testRenderMixedEditableAndNotEditable(): void
    {
        $data = [
            [
                'source'      => 'en-US',
                'target'      => 'it-IT',
                'editable'    => true,
                'translation' => 'Yes',
                'TOT'         => 1,
                'involved_id' => '10',
            ],
            [
                'source'      => 'en-US',
                'target'      => 'it-IT',
                'editable'    => false,
                'translation' => 'No',
                'TOT'         => 2,
                'involved_id' => '11,12',
            ],
        ];

        $view   = new SegmentTranslationMismatches($data, $this->makeJobStruct(), 1, $this->featureSet(), $this->makeMetadataDao());
        $result = $view->render();

        $this->assertCount(1, $result['editable']);
        $this->assertCount(1, $result['not_editable']);
        $this->assertSame(1, $result['prop_available']);
    }

    /**
     * @throws Exception
     */
    public function testRenderThrowsWhenJobIdIsNull(): void
    {
        $job       = new JobStruct();
        $job->id   = null;
        $job->password = 'abc';

        $data = [
            [
                'source'      => 'en-US',
                'target'      => 'it-IT',
                'editable'    => true,
                'translation' => 'X',
                'TOT'         => 1,
                'involved_id' => '1',
            ],
        ];

        $view = new SegmentTranslationMismatches($data, $job, 0, $this->featureSet(), $this->makeMetadataDao());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JobStruct::$id must not be null');
        $view->render();
    }

    /**
     * @throws Exception
     */
    public function testRenderThrowsWhenJobPasswordIsNull(): void
    {
        $job           = new JobStruct();
        $job->id       = 1;
        $job->password = null;

        $data = [
            [
                'source'      => 'en-US',
                'target'      => 'it-IT',
                'editable'    => true,
                'translation' => 'X',
                'TOT'         => 1,
                'involved_id' => '1',
            ],
        ];

        $view = new SegmentTranslationMismatches($data, $job, 0, $this->featureSet(), $this->makeMetadataDao());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JobStruct::$password must not be null');
        $view->render();
    }

    /**
     * @throws Exception
     */
    public function testConstructorCreatesDefaultFeatureSet(): void
    {
        // FeatureSet is now required — verify it works when explicitly passed
        $view   = new SegmentTranslationMismatches([], $this->makeJobStruct(), 0, $this->featureSet(), $this->makeMetadataDao());
        $result = $view->render();

        $this->assertIsArray($result);
    }

    /**
     * @throws Exception
     */
    public function testConstructorAcceptsExplicitFeatureSet(): void
    {
        $featureSet = new FeatureSet($this->createStub(\Model\DataAccess\IDatabase::class));
        $view       = new SegmentTranslationMismatches([], $this->makeJobStruct(), 0, $featureSet, $this->makeMetadataDao());
        $result     = $view->render();

        $this->assertIsArray($result);
    }
}
