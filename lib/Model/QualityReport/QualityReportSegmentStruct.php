<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 24/07/2018
 * Time: 12:46
 */

namespace Model\QualityReport;

use Exception;
use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDaoStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Utils\LQA\QA;
use Utils\Tools\PostEditing;
use View\API\V2\Json\QALocalWarning;


class QualityReportSegmentStruct extends AbstractDaoObjectStruct implements IDaoStruct
{


    public int $sid;

    public string $target;

    public string $segment;

    public ?string $segment_hash = null;

    public int $raw_word_count;

    public ?string $translation = null;

    public ?int $version; //unix timestamp of the last translation

    public bool $ice_locked;

    public string $status;

    public int $time_to_edit;

    public string $filename;

    public int $id_file;

    public bool $warning;

    public ?int $suggestion_match;

    public ?string $suggestion_source;

    public ?string $suggestion;

    public ?int $edit_distance;

    public bool $locked;

    public string $match_type;

    public array $warnings;

    public int $pee;

    public bool $ice_modified;

    public int $secs_per_word;

    public array $parsed_time_to_edit;

    public array $comments = [];

    public array $issues = [];

    public string $last_translation = '';

    public array $last_revisions = [];

    public int $pee_translation_revise;

    public int $pee_translation_suggestion;

    public int $version_number;

    public ?int $source_page;

    public bool $is_pre_translated = false;

    public array $dataRefMap = [];

    protected string $tm_analysis_status;

    /**
     * @return string
     */
    public function getTmAnalysisStatus(): string
    {
        return $this->tm_analysis_status;
    }

    /**
     * @return float
     */
    public function getSecsPerWord(): float
    {
        $val = @round(($this->time_to_edit / 1000) / $this->raw_word_count, 1);

        return ($val != INF ? $val : 0.0);
    }

    public function isICEModified(): bool
    {
        return ($this->version_number != 0 && $this->isICE());
    }

    public function isICE(): bool
    {
        return ($this->match_type == 'ICE' && $this->locked);
    }

    /**
     * @return int
     */
    public function getPEE(): int
    {
        if (empty($this->translation) || empty($this->suggestion)) {
            return 0;
        }

        return PostEditing::getPee($this->suggestion, $this->translation, $this->target);
    }

    public function getPEEBwtTranslationSuggestion(): int
    {
        if (empty($this->last_translation)) {
            return 0;
        }

        return PostEditing::getPee($this->suggestion, $this->last_translation, $this->target);
    }

    public function getPEEBwtTranslationRevise(): int
    {
        if (empty($this->last_translation) or empty($this->last_revisions)) {
            return 0;
        }

        $last_revision_record = end($this->last_revisions);
        $last_revision = $last_revision_record['translation'];

        return PostEditing::getPee($this->last_translation, $last_revision, $this->target);
    }

    /**
     * @throws Exception
     */
    public function getLocalWarning(FeatureSet $featureSet, JobStruct $chunk): array
    {
        // When the query for segments is performed, a condition is added to get NULL instead of the translation when the status is NEW
        // so that the local warning check is not displayed/needed
        if (is_null($this->translation)) {
            return [];
        }

        $QA = new QA($this->segment, $this->translation);
        $QA->setSourceSegLang($chunk->source);
        $QA->setTargetSegLang($chunk->target);
        $QA->setChunk($chunk);
        $QA->setFeatureSet($featureSet);
        $QA->performConsistencyCheck();

        $local_warning = new QALocalWarning($QA, $this->sid, $chunk->id_project);

        return $local_warning->render();
    }
}
