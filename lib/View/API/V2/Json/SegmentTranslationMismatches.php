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
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao;

class SegmentTranslationMismatches
{

    protected array $data;
    protected int $thereArePropagations;
    protected ?FeatureSet $featureSet;
    private JobStruct $jobStruct;

    /**
     * SegmentTranslationMismatches constructor.
     * from query: getWarning(id_job, password)
     *
     * @param array $Translation_mismatches
     * @param JobStruct $jobStruct
     * @param int $thereArePropagations
     * @param FeatureSet|null $featureSet
     */
    public function __construct(array $Translation_mismatches, JobStruct $jobStruct, int $thereArePropagations, FeatureSet $featureSet = null)
    {
        $this->data = $Translation_mismatches;
        $this->thereArePropagations = $thereArePropagations;
        if ($featureSet == null) {
            $featureSet = new FeatureSet();
        }
        $this->featureSet = $featureSet;
        $this->jobStruct = $jobStruct;
    }

    /**
     * @return array
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
        $metadataDao = new MetadataDao();

        foreach ($this->data as $row) {
            $Filter = MateCatFilter::getInstance(
                $featureSet,
                $row['source'],
                $row['target'],
                [],
                $metadataDao->getSubfilteringCustomHandlers($this->jobStruct->id, $this->jobStruct->password)
            );

            if ($row['editable']) {
                $result['editable'][] = [
                    'translation' => $Filter->fromLayer0ToLayer2($row['translation']),
                    'TOT' => $row['TOT'],
                    'involved_id' => explode(",", $row['involved_id'])
                ];
            } else {
                $result['not_editable'][] = [
                    'translation' => $Filter->fromLayer0ToLayer2($row['translation']),
                    'TOT' => $row['TOT'],
                    'involved_id' => explode(",", $row['involved_id'])
                ];
            }
        }

        return $result;
    }

}