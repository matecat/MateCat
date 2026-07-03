<?php


namespace Matecat\Core\Model\CustomizableXliff;

use Matecat\TestHelpers\AbstractTest;
use Model\Xliff\DTO\DefaultRule;
use PHPUnit\Framework\Attributes\Test;

class DefaultRuleCoverageTest extends AbstractTest
{
    #[Test]
    public function asEditorStatusReturnsApprovedForReviewedState(): void
    {
        $rule = new DefaultRule(['signed-off'], 'pre-translated');
        $this->assertEquals('APPROVED', $rule->asEditorStatus());
    }

    #[Test]
    public function asEditorStatusReturnsApproved2ForFinalState(): void
    {
        $rule = new DefaultRule(['final'], 'pre-translated');
        $this->assertEquals('APPROVED2', $rule->asEditorStatus());
    }

    #[Test]
    public function asEditorStatusReturnsNewForNewState(): void
    {
        $rule = new DefaultRule(['new'], 'pre-translated');
        $this->assertEquals('NEW', $rule->asEditorStatus());
    }

    #[Test]
    public function isTranslatedReturnsFalseWhenSourceEqualsTarget(): void
    {
        $rule = new DefaultRule([], 'pre-translated');
        $this->assertFalse($rule->isTranslated('same', 'same'));
    }

    #[Test]
    public function isTranslatedReturnsFalseWhenTargetEmpty(): void
    {
        $rule = new DefaultRule([], 'pre-translated');
        $this->assertFalse($rule->isTranslated('source', ''));
    }
}
