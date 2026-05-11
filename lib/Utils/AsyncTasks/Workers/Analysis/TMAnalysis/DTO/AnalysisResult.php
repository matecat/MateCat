<?php

namespace Utils\AsyncTasks\Workers\Analysis\TMAnalysis\DTO;

class AnalysisResult
{
    public function __construct(
        public int $id_segment,
        public int $id_job,
        public ?string $translation = null,
        public ?string $suggestion = null,
        public ?string $suggestions_array = null,
        public ?string $match_type = null,
        public float|int $eq_word_count = 0,
        public float|int $standard_word_count = 0,
        public ?string $tm_analysis_status = null,
        public ?int $warning = null,
        public ?string $serialized_errors_list = null,
        public ?float $mt_qe = null,
        public ?string $suggestion_source = null,
        public ?string $suggestion_match = null,
        public ?string $status = null,
        public ?bool $locked = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id_segment' => $this->id_segment,
            'id_job' => $this->id_job,
            'translation' => $this->translation,
            'suggestion' => $this->suggestion,
            'suggestions_array' => $this->suggestions_array,
            'match_type' => $this->match_type,
            'eq_word_count' => $this->eq_word_count,
            'standard_word_count' => $this->standard_word_count,
            'tm_analysis_status' => $this->tm_analysis_status,
            'warning' => $this->warning,
            'serialized_errors_list' => $this->serialized_errors_list,
            'mt_qe' => $this->mt_qe,
            'suggestion_source' => $this->suggestion_source,
            'suggestion_match' => $this->suggestion_match,
            'status' => $this->status,
            'locked' => $this->locked,
        ];
    }
}
