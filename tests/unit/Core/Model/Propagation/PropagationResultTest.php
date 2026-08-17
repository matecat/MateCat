<?php

namespace Matecat\Core\Model\Propagation;

use Matecat\TestHelpers\AbstractTest;
use Model\Propagation\PropagationResult;
use Model\Propagation\PropagationTotalStruct;
use PHPUnit\Framework\Attributes\Test;

/**
 * The typed replacement for the `array{totals?, propagated_ids?, segments_for_propagation?}` docblock
 * that `VersionHandlerInterface` used to declare.
 *
 * Two properties carry the whole reason this class exists, and both are pinned below:
 *
 * 1. `propagatedIds` is exposed **once**, at the top level. The struct it is built from writes the
 *    same list into two places (`$propagated_ids` and `$segments_for_propagation['propagated_ids']`),
 *    and three separate consumers read the top-level one while a single caller read the nested copy.
 *    The DTO makes the top-level value the only way to ask the question.
 * 2. `jsonSerialize()` must reproduce the legacy three-key array **exactly**, because the result is
 *    assigned straight into the `propagation` key of the `/api/app/set-translation` response body and
 *    `public/js/setTranslationUtil.js` reads it. A DTO that serialises differently is a wire change
 *    dressed up as a refactor.
 */
class PropagationResultTest extends AbstractTest
{
    #[Test]
    public function itCarriesTheThreeValuesItWasBuiltWith(): void
    {
        $result = new PropagationResult(
            ['total' => 3, 'repetitions_count' => 2, 'status' => 'TRANSLATED'],
            ['101', '202'],
            ['propagated' => ['ice' => [], 'not_ice' => []]]
        );

        $this->assertSame(['total' => 3, 'repetitions_count' => 2, 'status' => 'TRANSLATED'], $result->totals);
        $this->assertSame(['101', '202'], $result->propagatedIds);
        $this->assertSame(['propagated' => ['ice' => [], 'not_ice' => []]], $result->segmentsForPropagation);
    }

    /**
     * The empty case is not decoration: both controllers seed a propagation result before deciding
     * whether to propagate at all, so this is the shape a non-propagating request emits.
     */
    #[Test]
    public function anEmptyResultIsRepresentable(): void
    {
        $result = PropagationResult::empty();

        $this->assertSame([], $result->totals);
        $this->assertSame([], $result->propagatedIds);
        $this->assertSame([], $result->segmentsForPropagation);
    }

    #[Test]
    public function itReadsThePropagatedIdsFromTheStructsTopLevelList(): void
    {
        $struct = new PropagationTotalStruct();
        $struct->addPropagatedId('101');
        $struct->addPropagatedId('202');

        $result = PropagationResult::fromTotalStruct($struct);

        $this->assertSame(['101', '202'], $result->propagatedIds);
    }

    /**
     * Guards the choice of source. The struct duplicates the list into
     * `segments_for_propagation['propagated_ids']`; if the DTO were built from that nested copy
     * instead, this test still passes — so it is deliberately paired with the test below, which is
     * the one that can tell the two apart.
     */
    #[Test]
    public function itCarriesTotalsAndSegmentsForPropagationFromTheStruct(): void
    {
        $struct = new PropagationTotalStruct();
        $struct->setTotals(['total' => 5, 'repetitions_count' => 4, 'status' => 'TRANSLATED']);
        $struct->addPropagatedId('101');

        $result = PropagationResult::fromTotalStruct($struct);

        $this->assertSame(['total' => 5, 'repetitions_count' => 4, 'status' => 'TRANSLATED'], $result->totals);
        $this->assertSame($struct->getSegmentsForPropagation(), $result->segmentsForPropagation);
    }

    /**
     * The exact legacy array, key for key. `assertSame` rather than `assertEquals` so key **order**
     * is pinned too — `json_encode` preserves insertion order, so a reordering is observable to any
     * consumer that compares serialised bodies.
     */
    #[Test]
    public function itSerialisesToTheLegacyThreeKeyArray(): void
    {
        $result = new PropagationResult(
            ['total' => 1],
            ['101'],
            ['not_propagated' => ['ice' => [], 'not_ice' => []]]
        );

        $this->assertSame([
            'totals' => ['total' => 1],
            'propagated_ids' => ['101'],
            'segments_for_propagation' => ['not_propagated' => ['ice' => [], 'not_ice' => []]],
        ], $result->jsonSerialize());
    }

    /**
     * The serialised form is what reaches the browser, so it is asserted through `json_encode()` as
     * well — `jsonSerialize()` returning the right array is necessary but not sufficient if the class
     * ever stops implementing JsonSerializable.
     */
    #[Test]
    public function itEncodesAsAJsonObjectWithTheLegacyKeys(): void
    {
        $result = PropagationResult::fromTotalStruct(new PropagationTotalStruct());

        $encoded = json_encode($result);

        $this->assertIsString($encoded);
        $decoded = json_decode($encoded, true);

        $this->assertSame(['totals', 'propagated_ids', 'segments_for_propagation'], array_keys($decoded));
    }
}
