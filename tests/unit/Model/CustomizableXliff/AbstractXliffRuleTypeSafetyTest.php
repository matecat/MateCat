<?php

declare(strict_types=1);

namespace unit\Model\CustomizableXliff;

use DomainException;
use Model\Xliff\DTO\DefaultRule;
use Model\Xliff\DTO\Xliff12Rule;
use Model\Xliff\DTO\Xliff20Rule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AbstractXliffRuleTypeSafetyTest extends TestCase
{
    // ── $states structure ─────────────────────────────────────────

    #[Test]
    public function getStatesReturnsListOfStringsForStatesFilter(): void
    {
        $rule = new Xliff12Rule(['needs-l10n', 'exact-match'], 'pre-translated', 'translated', 'tm_100');

        $states = $rule->getStates('states');

        self::assertIsArray($states);
        self::assertContainsOnlyString($states);
        self::assertSame(['needs-l10n'], $states);
    }

    #[Test]
    public function getStatesReturnsListOfStringsForQualifiersFilter(): void
    {
        $rule = new Xliff12Rule(['needs-l10n', 'exact-match'], 'pre-translated', 'translated', 'tm_100');

        $qualifiers = $rule->getStates('state-qualifiers');

        self::assertIsArray($qualifiers);
        self::assertContainsOnlyString($qualifiers);
        self::assertSame(['exact-match'], $qualifiers);
    }

    #[Test]
    public function getStatesReturnsMergedListWhenNoFilter(): void
    {
        $rule = new Xliff12Rule(['needs-l10n', 'exact-match'], 'pre-translated', 'translated', 'tm_100');

        $all = $rule->getStates();

        self::assertIsArray($all);
        self::assertContainsOnlyString($all);
        self::assertCount(2, $all);
    }

    #[Test]
    public function getStatesReturnsEmptyArrayWhenNoStatesMatch(): void
    {
        $rule = new Xliff12Rule(['exact-match'], 'pre-translated', 'translated', 'tm_100');

        self::assertSame([], $rule->getStates('states'));
        self::assertSame(['exact-match'], $rule->getStates('state-qualifiers'));
    }

    // ── jsonSerialize / getArrayCopy shape ────────────────────────

    #[Test]
    public function jsonSerializeReturnsExpectedShapeForPreTranslated(): void
    {
        $rule = new Xliff12Rule(['needs-l10n'], 'pre-translated', 'translated', 'tm_100');

        $result = $rule->jsonSerialize();

        self::assertArrayHasKey('states', $result);
        self::assertArrayHasKey('analysis', $result);
        self::assertArrayHasKey('editor', $result);
        self::assertArrayHasKey('match_category', $result);
        self::assertIsArray($result['states']);
        self::assertContainsOnlyString($result['states']);
        self::assertSame('pre-translated', $result['analysis']);
        self::assertSame('translated', $result['editor']);
        self::assertSame('tm_100', $result['match_category']);
    }

    #[Test]
    public function jsonSerializeOmitsEditorAndMatchCategoryForNew(): void
    {
        $rule = new Xliff12Rule(['final'], 'new');

        $result = $rule->jsonSerialize();

        self::assertArrayHasKey('states', $result);
        self::assertArrayHasKey('analysis', $result);
        self::assertArrayNotHasKey('editor', $result);
        self::assertArrayNotHasKey('match_category', $result);
        self::assertSame('new', $result['analysis']);
    }

    #[Test]
    public function getArrayCopyReturnsSameAsJsonSerialize(): void
    {
        $rule = new Xliff12Rule(['needs-l10n'], 'pre-translated', 'translated', 'tm_100');

        self::assertSame($rule->jsonSerialize(), $rule->getArrayCopy());
    }

    // ── fromArray factory ─────────────────────────────────────────

    #[Test]
    public function fromArrayCreatesXliff12RuleCorrectly(): void
    {
        $rule = Xliff12Rule::fromArray([
            'states'   => ['needs-l10n'],
            'analysis' => 'pre-translated',
            'editor'   => 'translated',
            'match_category' => 'tm_100',
        ]);

        self::assertInstanceOf(Xliff12Rule::class, $rule);
        self::assertSame(['needs-l10n'], $rule->getStates('states'));
    }

    #[Test]
    public function fromArrayCreatesXliff20RuleCorrectly(): void
    {
        $rule = Xliff20Rule::fromArray([
            'states'   => ['final'],
            'analysis' => 'pre-translated',
            'editor'   => 'approved',
        ]);

        self::assertInstanceOf(Xliff20Rule::class, $rule);
        self::assertSame(['final'], $rule->getStates('states'));
    }

    #[Test]
    public function fromArrayCreatesDefaultRuleCorrectly(): void
    {
        $rule = DefaultRule::fromArray([
            'states'   => ['translated'],
            'analysis' => 'pre-translated',
        ]);

        self::assertInstanceOf(DefaultRule::class, $rule);
        self::assertSame(['translated'], $rule->getStates('states'));
    }

    // ── Xliff20Rule (no dedicated test file) ──────────────────────

    #[Test]
    public function xliff20RuleAcceptsValidStates(): void
    {
        $rule = new Xliff20Rule(['initial'], 'new');

        self::assertSame(['initial'], $rule->getStates('states'));
        self::assertFalse($rule->isTranslated('source', 'target'));
        self::assertSame('NEW', $rule->asEditorStatus());
    }

    #[Test]
    public function xliff20RuleRejectsInvalidStates(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Wrong state value');

        // 'needs-l10n' is XLIFF 1.2 only, not valid for XLIFF 2.0
        new Xliff20Rule(['needs-l10n'], 'new');
    }

    // ── payable_rates parameter shape ─────────────────────────────

    #[Test]
    public function asStandardWordCountAcceptsStringKeyedPayableRates(): void
    {
        $rule = new Xliff12Rule(['needs-l10n'], 'pre-translated', 'translated', 'tm_100');

        $rates = ['100%' => 100, 'ICE' => 0, '95%-99%' => 85];
        $result = $rule->asStandardWordCount(100, $rates);

        self::assertIsFloat($result);
        self::assertSame(100.0, $result);
    }

    #[Test]
    public function asEquivalentWordCountAcceptsStringKeyedPayableRates(): void
    {
        $rule = new Xliff12Rule(['needs-l10n'], 'pre-translated', 'translated', 'tm_100');

        $rates = ['100%' => 50, 'ICE' => 0];
        $result = $rule->asEquivalentWordCount(200, $rates);

        self::assertIsFloat($result);
        self::assertSame(100.0, $result);
    }
}
