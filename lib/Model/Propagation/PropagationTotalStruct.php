<?php

namespace Model\Propagation;

use JsonSerializable;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;
use Model\Translations\SegmentTranslationStruct;

class PropagationTotalStruct extends AbstractDaoSilentStruct implements IDaoStruct, JsonSerializable
{

    /**
     * @var array{total?: int, repetitions_count?: int}
     */
    protected array $totals = [];

    /**
     * @var list<string>
     */
    protected array $propagated_ids = [];

    /**
     * @var array<string, string>
     */
    protected array $propagated_ids_to_update_version = [];

    /**
     * @var array<string, mixed>
     */
    protected array $segments_for_propagation = [
        'propagated' => [
            'ice' => [],
            'not_ice' => [],
        ],
        'not_propagated' => [
            'ice' => [],
            'not_ice' => [],
        ],
    ];

    /**
     * @return array{total?: int, repetitions_count?: int}
     */
    public function getTotals(): array
    {
        return $this->totals;
    }

    /**
     * `repetitions_count` is how many segments in the chunk repeat this one, the current segment
     * excluded; `total` is their summed word count, equivalent or raw depending on the project setting.
     * Both count every repetition, including the ones propagation left untouched.
     *
     * A third key, `status`, used to travel with them. It read the third column of the rollup row,
     * which is `id_segment` and not `status`, and a super-aggregate row holds NULL there — so it
     * had always emitted null.
     *
     * @param array{total: int, repetitions_count: int} $params
     */
    public function setTotals(array $params): void
    {
        $this->totals['total'] = $params['total'];
        $this->totals['repetitions_count'] = $params['repetitions_count'];
    }

    /**
     * @return list<string>
     */
    public function getPropagatedIds(): array
    {
        return $this->propagated_ids;
    }

    /**
     * @param string $id_segment
     */
    public function addPropagatedId(string $id_segment): void
    {
        if (false === in_array($id_segment, $this->propagated_ids)) {
            $this->propagated_ids[] = $id_segment;
        }
    }

    /**
     * @return array<string, string>
     */
    public function getPropagatedIdsToUpdateVersion(): array
    {
        return $this->propagated_ids_to_update_version;
    }

    /**
     * @param string $id_segment
     */
    public function addPropagatedIdToUpdateVersion(string $id_segment): void
    {
        if (false === in_array($id_segment, $this->propagated_ids_to_update_version)) {
            $this->propagated_ids_to_update_version[$id_segment] = $id_segment;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getSegmentsForPropagation(): array
    {
        return $this->segments_for_propagation;
    }

    /**
     * @param SegmentTranslationStruct $segmentTranslation
     */
    public function addPropagatedIce(SegmentTranslationStruct $segmentTranslation): void
    {
        $this->segments_for_propagation['propagated']['ice']['id'][] = $segmentTranslation->id_segment;
        $this->segments_for_propagation['propagated']['ice']['object'][] = $segmentTranslation;
        $this->segments_for_propagation['propagated']['ice']['eq_word_count'][] = $segmentTranslation->eq_word_count;
    }

    /**
     * @param SegmentTranslationStruct $segmentTranslation
     */
    public function addNotPropagatedIce(SegmentTranslationStruct $segmentTranslation): void
    {
        $this->segments_for_propagation['not_propagated']['ice']['id'][] = $segmentTranslation->id_segment;
        $this->segments_for_propagation['not_propagated']['ice']['object'][] = $segmentTranslation;
    }

    /**
     * @param SegmentTranslationStruct $segmentTranslation
     */
    public function addPropagatedNotIce(SegmentTranslationStruct $segmentTranslation): void
    {
        $this->segments_for_propagation['propagated']['not_ice']['id'][] = $segmentTranslation->id_segment;
        $this->segments_for_propagation['propagated']['not_ice']['object'][] = $segmentTranslation;
        $this->segments_for_propagation['propagated']['not_ice']['eq_word_count'][] = $segmentTranslation->eq_word_count;
    }

    /**
     * @param SegmentTranslationStruct $segmentTranslation
     */
    public function addNotPropagatedNotIce(SegmentTranslationStruct $segmentTranslation): void
    {
        $this->segments_for_propagation['not_propagated']['not_ice']['id'][] = $segmentTranslation->id_segment;
        $this->segments_for_propagation['not_propagated']['not_ice']['object'][] = $segmentTranslation;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            "totals" => $this->totals,
            "propagated_ids" => $this->propagated_ids,
            "propagated_ids_to_update_version" => $this->propagated_ids_to_update_version,
            "segments_for_propagation" => $this->segments_for_propagation,
        ];
    }

    /**
     * @return list<SegmentTranslationStruct>
     */
    public function getAllToPropagate(): array
    {
        $aggregator = [];

        if (!empty($this->segments_for_propagation['propagated']['ice']['object'])) {
            foreach ($this->segments_for_propagation['propagated']['ice']['object'] as $segment) {
                $aggregator[] = $segment;
            }
        }

        if (!empty($this->segments_for_propagation['propagated']['not_ice']['object'])) {
            foreach ($this->segments_for_propagation['propagated']['not_ice']['object'] as $segment) {
                $aggregator[] = $segment;
            }
        }

        return $aggregator;
    }

}