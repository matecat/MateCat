<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 13/03/18
 * Time: 16.39
 *
 */

namespace View\API\V2\Json;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\DataAccess\ShapelessConcreteStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao;
use RuntimeException;

class SegmentTranslationMismatches
{

    /** @var array<int, ShapelessConcreteStruct|array<string, mixed>> */
    protected array $data;
    protected int $thereArePropagations;
    protected ?FeatureSet $featureSet;
    private JobStruct $jobStruct;
    private ?MetadataDao $metadataDao;

    /**
     * SegmentTranslationMismatches constructor.
     * from query: getWarning(id_job, password)
     *
     * @param array<int, ShapelessConcreteStruct|array<string, mixed>> $Translation_mismatches
     * @param JobStruct $jobStruct
     * @param int $thereArePropagations
     * @param FeatureSet|null $featureSet
     * @param MetadataDao|null $metadataDao
     * @throws Exception
     */
    public function __construct(array $Translation_mismatches, JobStruct $jobStruct, int $thereArePropagations, FeatureSet $featureSet = null, MetadataDao $metadataDao = null)
    {
        $this->data = $Translation_mismatches;
        $this->thereArePropagations = $thereArePropagations;
        if ($featureSet == null) {
            $featureSet = new FeatureSet();
        }
        $this->featureSet = $featureSet;
        $this->jobStruct = $jobStruct;
        $this->metadataDao = $metadataDao;
    }

    /**
     * @return array<string, mixed>
     * @throws Exception
     */
    public function render(): array
    {
        $result = [
            'editable' => [],
            'not_editable' => [],
            'prop_available' => $this->thereArePropagations
        ];

        $featureSet = ($this->featureSet !== null) ? $this->featureSet : new FeatureSet();
        $metadataDao = $this->metadataDao ?? new MetadataDao();

        $jobId = $this->jobStruct->id ?? throw new RuntimeException('JobStruct::$id must not be null');
        $jobPassword = $this->jobStruct->password ?? throw new RuntimeException('JobStruct::$password must not be null');

        foreach ($this->data as $row) {
            $filter = MateCatFilter::getInstance(
                $featureSet,
                $row['source'],
                $row['target'],
                [],
                $metadataDao->getSubfilteringCustomHandlers($jobId, $jobPassword)
            );

            if (!$filter instanceof MateCatFilter) {
                throw new RuntimeException('Expected MateCatFilter instance from getInstance()');
            }

            if ($row['editable']) {
                $result['editable'][] = [
                    'translation' => $filter->fromLayer0ToLayer2($row['translation']),
                    'TOT' => $row['TOT'],
                    'involved_id' => explode(",", $row['involved_id'])
                ];
            } else {
                $result['not_editable'][] = [
                    'translation' => $filter->fromLayer0ToLayer2($row['translation']),
                    'TOT' => $row['TOT'],
                    'involved_id' => explode(",", $row['involved_id'])
                ];
            }
        }

        return $result;
    }

}