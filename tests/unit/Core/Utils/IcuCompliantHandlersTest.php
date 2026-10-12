<?php

namespace Matecat\Core\Utils;

use Matecat\SubFiltering\Enum\InjectableFiltersTags;
use Matecat\TestHelpers\AbstractTest;
use PHPUnit\Framework\Attributes\Test;
use Utils\Subfiltering\IcuCompliantHandlers;

/**
 * The handler list travels to MyMemory next to the Layer 1 text: MyMemory decodes what it
 * receives, queries its index and re-encodes the matches with the handlers it was given.
 * It has no ICU flag of its own, so for an ICU segment MateCat must send the list already
 * reduced to the ICU-compliant handlers — the same set that produced the text.
 *
 * The two sentinel values of that wire field drive the whole reduction: `null` means "load
 * no handlers", an empty array means "load your defaults".
 */
class IcuCompliantHandlersTest extends AbstractTest
{
    #[Test]
    public function nullIsLeftAloneBecauseNoHandlersAreLoadedOnEitherSide(): void
    {
        $this->assertNull(IcuCompliantHandlers::reduceToIcuCompliant(null));
    }

    #[Test]
    public function theDefaultSetIsSentExplicitlyInsteadOfAsAnEmptyArray(): void
    {
        // An empty array would make MyMemory reload its full default set, four members of
        // which are not ICU-compliant.
        $this->assertSame(
            [InjectableFiltersTags::markup->value],
            IcuCompliantHandlers::reduceToIcuCompliant([])
        );
    }

    #[Test]
    public function nonCompliantHandlersAreDroppedFromAnExplicitList(): void
    {
        $reduced = IcuCompliantHandlers::reduceToIcuCompliant([
            InjectableFiltersTags::single_curly->value,
            InjectableFiltersTags::markup->value,
            InjectableFiltersTags::sprintf->value,
        ]);

        $this->assertSame([InjectableFiltersTags::markup->value], $reduced);
    }

    #[Test]
    public function aListThatReducesToNothingBecomesNullNotAnEmptyArray(): void
    {
        // `[]` on the wire means "defaults", so an empty result must be expressed as null
        // or MyMemory would load every default handler back.
        $this->assertNull(IcuCompliantHandlers::reduceToIcuCompliant([
            InjectableFiltersTags::single_curly->value,
            InjectableFiltersTags::twig->value,
        ]));
    }

    #[Test]
    public function anAlreadyCompliantListIsUnchanged(): void
    {
        $this->assertSame(
            [InjectableFiltersTags::markup->value],
            IcuCompliantHandlers::reduceToIcuCompliant([InjectableFiltersTags::markup->value])
        );
    }

    #[Test]
    public function unknownTagNamesResolveTheSameWayTheFilterResolvesThem(): void
    {
        // AbstractFilter::getInstance() maps tag names to handler classes and falls back to
        // the default set when nothing maps, so MyMemory would do the same with this list.
        // The reduction has to mirror that, not invent a stricter rule.
        $this->assertSame(
            [InjectableFiltersTags::markup->value],
            IcuCompliantHandlers::reduceToIcuCompliant(['not_a_handler'])
        );
    }
}
