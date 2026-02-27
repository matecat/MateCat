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

        // TODO implement the syntax check algorithms in the backend too and count characters at runtime instead of use the stored values
        //  because some segments might not have been opened and the error list could be empty

        // If there are ERR_SIZE_RESTRICTION errors, return true.
        // This check is here because, at this point, the backend doesn't know the segment's character count.
        // It is calculated only on the frontend, using an algorithm implemented only on the frontend.
        // Since we need the string length to check for the error in the QA class, we can't compute it here.
        // We get the error from `segment_translations.serialized_errors_list`.
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

        // In Matecat, some special characters are mapped in data_ref_map (for example, &#39;)
        // and can be omitted in the target.
        // In this case, |||UNTRANSLATED_CONTENT_START||| should not be found in the target.
        //
        // To skip these characters, the QA class needs the replaced versions of the segment and the target
        // for the _addThisElementToDomMap() function.
        if (!empty($dataRefMap)) {
            $dataRefReplacer = new DataRefReplacer($dataRefMap);
            $segment = $dataRefReplacer->replace($segment);
            $translation = $dataRefReplacer->replace($translation);
        }

        // We must perform a new validation here, ignoring `$error` from `segment_translations.serialized_errors_list`
        // because some segments might not have been opened and the error list could be empty.
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