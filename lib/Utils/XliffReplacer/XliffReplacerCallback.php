<?php

namespace Utils\XliffReplacer;

use Exception;
use Matecat\ICU\MessagePatternComparator;
use Matecat\ICU\MessagePatternValidator;
use Matecat\SubFiltering\MateCatFilter;
use Matecat\SubFiltering\Utils\DataRefReplacer;
use Matecat\XliffParser\XliffReplacer\XliffReplacerCallbackInterface;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Jobs\MetadataDao;
use Utils\LQA\ICUSourceSegmentChecker;
use Utils\LQA\QA;

class XliffReplacerCallback implements XliffReplacerCallbackInterface
{
    use ICUSourceSegmentChecker;


    /**
     * @var array
     */
    private array $subfilteringCustomHandlers;


    /**
     * @var string
     */
    private string $sourceLang;

    /**
     * @var string
     */
    private string $targetLang;

    private FeatureSet $featureSet;
    private ?JobStruct $jobStruct;

    /**
     * XliffReplacerCallback constructor.
     *
     * @param FeatureSet $featureSet
     * @param string $sourceLang
     * @param string $targetLang
     * @param JobStruct $jobStruct
     */
    public function __construct(FeatureSet $featureSet, string $sourceLang, string $targetLang, JobStruct $jobStruct)
    {
        $this->featureSet = $featureSet;
        $this->sourceLang = $sourceLang;
        $this->targetLang = $targetLang;
        $this->jobStruct = $jobStruct;

        $metadataDao = new MetadataDao();
        $this->subfilteringCustomHandlers = $metadataDao->getSubfilteringCustomHandlers($jobStruct->id, $jobStruct->password);

    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function thereAreErrors(int $segmentId, string $segment, string $translation, ?array $dataRefMap = [], ?string $error = null): bool
    {

        // if there are ERR_SIZE_RESTRICTION errors, return true
        if ($error !== null) {
            $errors = json_decode($error);

            if ($errors) {
                foreach ($errors as $err) {
                    if (isset($err->outcome) and $err->outcome === QA::ERR_SIZE_RESTRICTION) {
                        return true;
                    }
                }
            }
        }

        $filter = MateCatFilter::getInstance(
            $this->featureSet,
            $this->sourceLang,
            $this->targetLang,
            $dataRefMap ?? [],
            $this->subfilteringCustomHandlers,
            $this->sourceContainsIcu($this->jobStruct->getProject(), $this->jobStruct, $segment)
        );

        $segment = $filter->fromLayer0ToLayer1($segment);
        $translation = $filter->fromLayer0ToLayer1($translation);

        //
        // ------------------------------------
        // NOTE 2021-01-25
        // ------------------------------------
        //
        // In Matecat there are some special characters mapped in data_ref_map (like &#39; for example)
        // that can be omitted in the target.
        // In this case no |||UNTRANSLATED_CONTENT_START||| should be found in the target
        //
        // To skip these characters QA class needs replaced version of segment and target for _addThisElementToDomMap() function
        //
        if (!empty($dataRefMap)) {
            $dataRefReplacer = new DataRefReplacer($dataRefMap);
            $segment = $dataRefReplacer->replace($segment);
            $translation = $dataRefReplacer->replace($translation);
        }

        $check = new QA(
            $segment,
            $translation,
            MessagePatternComparator::fromValidators(
                $this->icuSourcePatternValidator,
                new MessagePatternValidator(
                    $this->jobStruct->target,
                    $translation
                )
            ),
            // ICU syntax is enabled for this project, and the translation content must contain valid ICU syntax
            $this->sourceContainsIcu
        ); // Layer 1 here

        $check->setFeatureSet($this->featureSet);
        $check->setTargetSegLang($this->targetLang);
        $check->setSourceSegLang($this->sourceLang);
        $check->performConsistencyCheck();

        return $check->thereAreErrors();
    }

}