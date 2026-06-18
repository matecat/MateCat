<?php

namespace View\API\V2\Json;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\DataAccess\AbstractDaoObjectStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao;
use Model\LQA\EntryStruct;
use RuntimeException;

class SegmentVersion
{

    private FeatureSet $featureSet;
    /**
     * @var AbstractDaoObjectStruct[]
     */
    private array $data;
    private bool $with_issues;

    /**
     * @var JobStruct
     */
    private JobStruct $chunk;

    private ?MetadataDao $metadataDao;

    /**
     * SegmentVersionController constructor.
     *
     * @param JobStruct $chunk
     * @param AbstractDaoObjectStruct[] $data
     * @param bool $with_issues
     * @param FeatureSet $featureSet
     * @param MetadataDao|null $metadataDao
     */
    public function __construct(JobStruct $chunk, array $data, ?bool $with_issues, FeatureSet $featureSet, ?MetadataDao $metadataDao = null)
    {
        $this->data = $data;
        $this->with_issues = $with_issues ?? false;
        $this->chunk = $chunk;
        $this->metadataDao = $metadataDao;
        $this->featureSet = $featureSet;
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws Exception
     */
    public function render(): array
    {
        if ($this->with_issues) {
            return $this->renderItemsWithIssues();
        }

        return $this->renderItemsNormal();
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws Exception
     */
    protected function renderItemsWithIssues(): array
    {
        $out = [];
        $issuesSubset = [];

        $versionId = null;
        $version = null;

        $issues_renderer = new SegmentTranslationIssue();

        foreach ($this->data as $record) {
            if (!is_null($versionId) && $versionId != $record->id) {
                if ($version !== null && !empty($issuesSubset)) {
                    // attach issues to version
                    $version['issues'] = array_map(function ($item) use ($issues_renderer) {
                        return $issues_renderer->renderItem($item);
                    }, $issuesSubset);
                }

                if ($version !== null) {
                    $out[] = $version;
                }

                $issuesSubset = [];
            }

            $version = $this->renderItem($record);

            $version['issues'] = [];

            if (!isset($version['diff'])) {
                $version['diff'] = json_decode($record->raw_diff ?? 'null', true);
            }

            if (!is_null($record->qa_id_segment)) {
                $issuesSubset[] = (new EntryStruct([
                    'uid' => $record->qa_uid,
                    'id' => $record->qa_id,
                    'id_segment' => $record->qa_id_segment,
                    'id_job' => $record->qa_id_job,
                    'id_category' => $record->qa_id_category,
                    'severity' => $record->qa_severity,
                    'translation_version' => $record->qa_translation_version,
                    'start_node' => $record->qa_start_node,
                    'start_offset' => $record->qa_start_offset,
                    'end_node' => $record->qa_end_node,
                    'end_offset' => $record->qa_end_offset,
                    'is_full_segment' => $record->qa_is_full_segment,
                    'penalty_points' => $record->qa_penalty_points,
                    'comment' => $record->qa_comment,
                    'create_date' => $record->qa_create_date,
                    'target_text' => $record->qa_target_text,
                    'source_page' => $record->qa_source_page
                ]))->setDiff($version['diff']);
            }

            $versionId = $record->id;
        }

        if ($version !== null) {
            if (!empty($issuesSubset)) {
                $version['issues'] = array_map(function ($item) use ($issues_renderer) {
                    return $issues_renderer->renderItem($item);
                }, $issuesSubset);
            }

            $out[] = $version;
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws Exception
     */
    protected function renderItemsNormal(): array
    {
        $out = [];
        foreach ($this->data as $record) {
            $out[] = $this->renderItem($record);
        }

        return $out;
    }

    /**
     * @param AbstractDaoObjectStruct $version
     *
     * @return array<string, mixed>
     * @throws Exception
     */
    public function renderItem(AbstractDaoObjectStruct $version): array
    {
        $this->metadataDao ??= new MetadataDao();
        $Filter = MateCatFilter::getInstance(
            $this->featureSet,
            $this->chunk->source,
            $this->chunk->target,
            [],
            $this->metadataDao->getSubfilteringCustomHandlers((int)$this->chunk->id, (string)$this->chunk->password)
        );

        if (!$Filter instanceof MateCatFilter) {
            throw new RuntimeException('Expected MateCatFilter instance from getInstance()');
        }

        return [
            'id' => (int)$version->id,
            'id_segment' => (int)$version->id_segment,
            'id_job' => (int)$version->id_job,
            'translation' => $Filter->fromLayer0ToLayer2($version->translation ?? ''),
            'version_number' => (int)$version->version_number,
            'propagated_from' => (int)$version->propagated_from,
            'created_at' => $version->creation_date,
        ];
    }

}
