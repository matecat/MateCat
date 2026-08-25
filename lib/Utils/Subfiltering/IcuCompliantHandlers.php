<?php

namespace Utils\Subfiltering;

use Matecat\SubFiltering\Enum\InjectableFiltersTags;
use Matecat\SubFiltering\HandlersSorter;

/**
 * Reduces a subfiltering handler list to the handlers that leave ICU syntax intact.
 *
 * MateCat builds the Layer 1 text of an ICU segment with the ICU-compliant handlers only,
 * but the list that travels to MyMemory next to that text carries no ICU flag: MyMemory
 * decodes what it receives, queries its index and re-encodes the matches with whatever
 * handlers it was given. Handed the full project list it would bring the matches back with
 * the ICU arguments wrapped in PH tags, so the list itself has to be reduced before it
 * leaves.
 *
 * Both sentinel values of that field are preserved: `null` asks for no handlers at all,
 * an empty array asks for the default set.
 */
class IcuCompliantHandlers
{
    /**
     * @param array<int|string, mixed>|null $tagNames Handler tag names as they travel on the wire.
     *
     * @return array<int, string>|null The reduced list, or null when no handler survives.
     */
    public static function reduceToIcuCompliant(?array $tagNames): ?array
    {
        if ($tagNames === null) {
            // No handlers are loaded on either side, so there is nothing to keep in step.
            return null;
        }

        // The literal is HandlersSorter's $icu_enabled, and it is the reduction itself: with
        // false the sorter hands back the same list, so this method would return its own input.
        // Whether a segment needs the reduction is the caller's question, answered against a
        // flag that may be missing, false or true; by the time we are here it is settled.
        //
        // resolveClassNames() is the same entry point AbstractFilter::getInstance() resolves
        // its own handlers through, down to the fallback to the default set for a list that
        // maps to nothing. Going through it is what keeps the two sides equal.
        $reduced = InjectableFiltersTags::tagNamesForArrayClasses(
            HandlersSorter::resolveClassNames(array_values(array_filter($tagNames, 'is_string')), true)
        );

        // An empty array would be read as "load the defaults", which is the opposite of
        // what an empty reduction means, so it has to travel as null instead.
        return empty($reduced) ? null : $reduced;
    }
}
