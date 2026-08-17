<?php

namespace Model\Propagation;

use JsonSerializable;

/**
 * The result of propagating one translation to its repetitions.
 *
 * Replaces the `array{totals?: ..., propagated_ids?: ..., segments_for_propagation?: ...}` docblock
 * that `VersionHandlerInterface` used to declare. The shape was optional-keyed, so every reader had to
 * guard each access and no reader could be sure which keys a given code path had actually produced.
 *
 * `propagatedIds` is deliberately exposed **once**. `PropagationTotalStruct` used to write the same
 * list twice — into its own `$propagated_ids` and again into
 * `$segments_for_propagation['propagated_ids']` — and readers split across the two copies: the editor
 * (`public/js/setTranslationUtil.js`) and `GetSearchController` read the top-level one, while
 * `SetTranslationController` read the nested one. Same values, two spellings, and nothing keeping them
 * in step beyond a single writer remembering to append twice.
 *
 * `jsonSerialize()` reproduces the legacy key names because this object is assigned straight into the
 * `propagation` key of the `/api/app/set-translation` and `/api/app/replace-all` response bodies, which
 * the editor reads. The property names are camelCase for PHP; the wire stays snake_case.
 */
final class PropagationResult implements JsonSerializable
{
    /**
     * @param array{total?: int, repetitions_count?: int, status?: string} $totals
     * @param list<string> $propagatedIds
     * @param array<string, mixed> $segmentsForPropagation
     */
    public function __construct(
        public readonly array $totals,
        public readonly array $propagatedIds,
        public readonly array $segmentsForPropagation,
    ) {
    }

    /**
     * The shape a request that did not propagate emits. Both controllers need it: they build a result
     * before deciding whether propagation applies, and the response must carry the key either way.
     */
    public static function empty(): self
    {
        return new self([], [], []);
    }

    public static function fromTotalStruct(PropagationTotalStruct $struct): self
    {
        return new self(
            $struct->getTotals(),
            $struct->getPropagatedIds(),
            $struct->getSegmentsForPropagation(),
        );
    }

    /**
     * @return array{
     *     totals: array{total?: int, repetitions_count?: int, status?: string},
     *     propagated_ids: list<string>,
     *     segments_for_propagation: array<string, mixed>
     * }
     */
    public function jsonSerialize(): array
    {
        return [
            'totals' => $this->totals,
            'propagated_ids' => $this->propagatedIds,
            'segments_for_propagation' => $this->segmentsForPropagation,
        ];
    }
}
