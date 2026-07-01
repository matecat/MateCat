<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 17/02/26
 * Time: 19:17
 *
 */

namespace Utils\LQA;

use Exception;
use Matecat\ICU\MessagePatternValidator;
use Model\DataAccess\IDatabase;
use Model\Jobs\JobStruct;
use Model\Projects\MetadataDao;
use Model\Projects\ProjectsMetadataMarshaller;
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
     * @throws Exception
     */
    private function sourceContainsIcu(ProjectStruct $projectStruct, JobStruct $chunk, string $sourceSegment, IDatabase $database): bool
    {
        $this->icuSourcePatternValidator = new MessagePatternValidator(
            $chunk->source,
            $sourceSegment,
        );

        $this->sourceContainsIcu = ICUSourceSegmentDetector::sourceContainsIcu(
            $this->icuSourcePatternValidator,
            $this->icuEnabled($projectStruct, $database)
        );

        return $this->sourceContainsIcu;

    }

    /**
     * @throws Exception
     */
    private function icuEnabled(ProjectStruct $projectStruct, IDatabase $database): bool
    {
        if ($this->icuEnabled !== null) {
            return $this->icuEnabled;
        }

        $icuEnabled = (new MetadataDao($database))->setCacheTTL(3600)->getValue((int)$projectStruct->id, ProjectsMetadataMarshaller::ICU_ENABLED->value);
        return $this->icuEnabled = (bool)$icuEnabled;
    }

}
