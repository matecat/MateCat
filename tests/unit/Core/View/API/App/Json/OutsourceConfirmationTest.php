<?php

namespace Matecat\Core\View\API\App\Json;

use Matecat\TestHelpers\AbstractTest;
use Model\Outsource\TranslatedConfirmationStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use View\API\App\Json\OutsourceConfirmation;

#[CoversClass(OutsourceConfirmation::class)]
class OutsourceConfirmationTest extends AbstractTest
{
    private function makeConfirmation(): TranslatedConfirmationStruct
    {
        $struct               = new TranslatedConfirmationStruct();
        $struct->id           = 42;
        $struct->id_job       = 10;
        $struct->password     = 'abc123';
        $struct->vendor_name  = 'Translated';
        $struct->id_vendor    = 1;
        $struct->create_date  = '2024-01-15 10:00:00';
        $struct->delivery_date = '2024-01-20 18:00:00';
        $struct->currency     = 'EUR';
        $struct->price        = 99.50;
        $struct->quote_pid    = 'pid_xyz';

        return $struct;
    }

    public function testRenderReturnsArray(): void
    {
        $view   = new OutsourceConfirmation($this->makeConfirmation());
        $result = $view->render();

        $this->assertIsArray($result);
    }

    public function testRenderIncludesTimestamps(): void
    {
        $struct = $this->makeConfirmation();
        $view   = new OutsourceConfirmation($struct);
        $result = $view->render();

        $this->assertArrayHasKey('create_timestamp', $result);
        $this->assertArrayHasKey('delivery_timestamp', $result);
        $this->assertIsInt($result['create_timestamp']);
        $this->assertIsInt($result['delivery_timestamp']);
    }

    public function testRenderIncludesQuoteReviewLink(): void
    {
        $struct = $this->makeConfirmation();
        $view   = new OutsourceConfirmation($struct);
        $result = $view->render();

        $this->assertArrayHasKey('quote_review_link', $result);
        $this->assertStringContainsString('pid_xyz', $result['quote_review_link']);
        $this->assertStringContainsString(TranslatedConfirmationStruct::REVIEW_ORDER_LINK, $result['quote_review_link']);
    }

    public function testRenderDoesNotContainId(): void
    {
        $view   = new OutsourceConfirmation($this->makeConfirmation());
        $result = $view->render();

        $this->assertArrayNotHasKey('id', $result);
    }

    public function testRenderPreservesJobId(): void
    {
        $view   = new OutsourceConfirmation($this->makeConfirmation());
        $result = $view->render();

        $this->assertArrayHasKey('id_job', $result);
        $this->assertSame(10, $result['id_job']);
    }
}
