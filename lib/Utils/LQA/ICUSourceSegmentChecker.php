<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 17/02/26
 * Time: 19:17
 *
 */

namespace Utils\LQA;

use Matecat\ICU\MessagePatternValidator;
use Model\Jobs\JobStruct;
use Model\Projects\MetadataDao as ProjectMetadataDao;
use Model\Projects\ProjectStruct;

/**
 * Provides functionality to validate whether a given source segment contains ICU patterns.
 * The trait performs syntax validation and checks if ICU is enabled in the project context.
 */
trait ICUSourceSegmentChecker
{

    private bool $sourceContainsIcu = false;
    private ?bool $icuEnabled = null;

    /** @noinspection PhpPrivateFieldCanBeLocalVariableInspection */
    private ?MessagePatternValidator $icuSourcePatternValidator = null;

    /**
     * Determines whether the source segment contains ICU patterns by validating its syntax.
     *
     * @param ProjectStruct $projectStruct The project structure containing configuration and settings.
     * @param JobStruct $chunk The job chunk containing the source data to validate.
     * @param string $sourceSegment The specific segment of the source to check for ICU patterns.
     * @return bool Returns true if the source segment contains ICU patterns, otherwise false.
     */
    private function sourceContainsIcu(ProjectStruct $projectStruct, JobStruct $chunk, string $sourceSegment): bool
    {
        $this->icuSourcePatternValidator = new MessagePatternValidator(
            $chunk->source,
            // Validate the ICU syntax in the segment to detect ICU patterns
            $sourceSegment,
        );

        if ($this->icuEnabled($projectStruct)) {
            $this->sourceContainsIcu = $this->icuSourcePatternValidator->containsComplexSyntax();
        }

        return $this->sourceContainsIcu;

    }

    /**
     * Determines if ICU is enabled for the given project.
     *
     * @param ProjectStruct $projectStruct The project structure containing metadata.
     * @return bool Returns true if ICU is enabled, otherwise false.
     */
    private function icuEnabled(ProjectStruct $projectStruct): bool
    {
        if ($this->icuEnabled !== null) {
            return $this->icuEnabled;
        }
        return $this->icuEnabled = $projectStruct->getMetadataValue(ProjectMetadataDao::ICU_ENABLED) ?? false;
    }

}