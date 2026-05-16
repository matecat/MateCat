<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 24/07/2018
 * Time: 12:46
 */

namespace Model\QualityReport;

use DivisionByZeroError;
use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\DataAccess\AbstractDaoObjectStruct;
use Model\DataAccess\IDaoStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao;
use Model\Segments\SegmentOriginalDataDao;
use Utils\LQA\QA;
use Utils\Tools\PostEditing;
use View\API\V2\Json\QALocalWarning;


class QualityReportSegmentStruct extends AbstractDaoObjectStruct implements IDaoStruct
{

    private ?MetadataDao $metadataDao = null;

    /** @param array<string, mixed> $array_params */
    public function __construct(array $array_params = [], ?MetadataDao $metadataDao = null)
    {
        parent::__construct($array_params);
        $this->metadataDao = $metadataDao;
    }


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

    /** @var array<string, mixed> */
    public array $warnings;

    public float $pee;

    public bool $ice_modified;

    public float $secs_per_word;

    /** @var list<string|int> */
    public array $parsed_time_to_edit;

    /** @var list<mixed> */
    public array $comments = [];

    /** @var list<mixed> */
    public array $issues = [];

    public string $last_translation = '';

    /** @var list<array{translation: string, source_page?: int, revision_number?: int|null}> */
    public array $last_revisions = [];

    public float $pee_translation_revise;

    public float $pee_translation_suggestion;

    public int $version_number;

    public ?int $source_page;

    public bool $is_pre_translated = false;

    /** @var array<string, string> */
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
      * @throws DivisionByZeroError
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
     * @return float
     */
    public function getPEE(): float
    {
        if (empty($this->translation) || empty($this->suggestion)) {
            return 0.0;
        }

        return PostEditing::getPee($this->suggestion, $this->translation, $this->target);
    }

    public function getPEEBwtTranslationSuggestion(): float
    {
        if (empty($this->last_translation) || empty($this->suggestion)) {
            return 0.0;
        }

        return PostEditing::getPee($this->suggestion, $this->last_translation, $this->target);
    }

    public function getPEEBwtTranslationRevise(): float
    {
        if (empty($this->last_translation) || empty($this->last_revisions)) {
            return 0.0;
        }

        $last_revision_record = end($this->last_revisions);
        $last_revision = $last_revision_record['translation'];

        return PostEditing::getPee($this->last_translation, $last_revision, $this->target);
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    public function getLocalWarning(FeatureSet $featureSet, JobStruct $chunk): array
    {
        // When the query for segments is performed, a condition is added to get NULL instead of the translation when the status is NEW
        // so that the local warning check is not displayed/needed
        if (is_null($this->translation) || $chunk->id === null || $chunk->password === null) {
            return [];
        }

        $metadata = $this->metadataDao ?? new MetadataDao();
        $dataRefMap = (!empty($this->sid)) ? SegmentOriginalDataDao::getSegmentDataRefMap($this->sid) : [];

        /** @var MateCatFilter $Filter */
        $Filter = MateCatFilter::getInstance($featureSet, $chunk->source, $chunk->target, $dataRefMap, $metadata->getSubfilteringCustomHandlers($chunk->id, $chunk->password));

        $src_content = $Filter->fromLayer0ToLayer2($this->segment);
        $trg_content = $Filter->fromLayer0ToLayer2($this->translation);

        $QA = new QA($src_content, $trg_content);
        $QA->setSourceSegLang($chunk->source);
        $QA->setTargetSegLang($chunk->target);
        $QA->setChunk($chunk);
        $QA->setFeatureSet($featureSet);
        $QA->performConsistencyCheck();

        return (new QALocalWarning($QA, $this->sid, $chunk->id_project, $Filter))->render();
    }
}
