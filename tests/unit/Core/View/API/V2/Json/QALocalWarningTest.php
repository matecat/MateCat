<?php

namespace Matecat\Core\View\API\V2\Json;

use Matecat\SubFiltering\MateCatFilter;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\CoversClass;
use Utils\LQA\QA;
use View\API\V2\Json\QALocalWarning;

#[CoversClass(QALocalWarning::class)]
class QALocalWarningTest extends AbstractTest
{
    public function testRenderWithNoNoticesReturnsNullDetailsAndZeroTotal(): void
    {
        $qa = $this->createStub(QA::class);
        $qa->method('thereAreNotices')->willReturn(false);

        $filter = $this->createStub(MateCatFilter::class);

        $view   = new QALocalWarning($qa, 1, 100, $filter);
        $result = $view->render();

        $this->assertIsArray($result);
        $this->assertNull($result['details']);
        $this->assertSame(0, $result['total']);
    }

    public function testRenderWithNoticesPopulatesDetails(): void
    {
        $qa = $this->createStub(QA::class);
        $qa->method('thereAreNotices')->willReturn(true);
        $qa->method('getExceptionList')->willReturn([
            QA::ERROR   => [],
            QA::WARNING => [],
            QA::INFO    => [],
        ]);
        $qa->method('getMalformedXmlStructs')->willReturn([
            'source' => [],
            'target' => [],
        ]);
        $qa->method('getTargetTagPositionError')->willReturn([]);
        $qa->method('getNoticesJSON')->willReturn('[]');

        $filter = $this->createStub(MateCatFilter::class);

        $view   = new QALocalWarning($qa, 5, 100, $filter);
        $result = $view->render();

        $this->assertIsArray($result);
        $this->assertIsArray($result['details']);
        $this->assertArrayHasKey('issues_info', $result['details']);
        $this->assertArrayHasKey('id_segment', $result['details']);
        $this->assertArrayHasKey('tag_mismatch', $result['details']);
        $this->assertSame(5, $result['details']['id_segment']);
        $this->assertSame(0, $result['total']);
    }

    public function testRenderTotalCountsNoticesFromJson(): void
    {
        $qa = $this->createStub(QA::class);
        $qa->method('thereAreNotices')->willReturn(true);
        $qa->method('getExceptionList')->willReturn([
            QA::ERROR   => [],
            QA::WARNING => [],
            QA::INFO    => [],
        ]);
        $qa->method('getMalformedXmlStructs')->willReturn([
            'source' => [],
            'target' => [],
        ]);
        $qa->method('getTargetTagPositionError')->willReturn([]);
        $qa->method('getNoticesJSON')->willReturn('[{"id":1},{"id":2}]');

        $filter = $this->createStub(MateCatFilter::class);

        $view   = new QALocalWarning($qa, 5, 100, $filter);
        $result = $view->render();

        $this->assertSame(2, $result['total']);
    }

    public function testRenderTagMismatchOrderIsPopulated(): void
    {
        $qa = $this->createStub(QA::class);
        $qa->method('thereAreNotices')->willReturn(true);
        $qa->method('getExceptionList')->willReturn([
            QA::ERROR   => [],
            QA::WARNING => [],
            QA::INFO    => [],
        ]);
        $qa->method('getMalformedXmlStructs')->willReturn([
            'source' => [],
            'target' => [],
        ]);
        $qa->method('getTargetTagPositionError')->willReturn(['a', 'b']);
        $qa->method('getNoticesJSON')->willReturn('[]');

        $filter = $this->createStub(MateCatFilter::class);

        $view   = new QALocalWarning($qa, 5, 100, $filter);
        $result = $view->render();

        $this->assertArrayHasKey('order', $result['details']['tag_mismatch']);
        $this->assertSame(['a', 'b'], $result['details']['tag_mismatch']['order']);
    }
}
