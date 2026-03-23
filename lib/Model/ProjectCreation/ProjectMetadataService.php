<?php

namespace Model\ProjectCreation;

use Exception;
use Model\Engines\Structs\EngineStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobsMetadataMarshaller;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Xliff\DTO\XliffRulesModel;
use Utils\Constants\EngineConstants;
use Utils\Engines\MyMemory;

class ProjectMetadataService
{
    public function __construct(
        private ProjectsMetadataDao $dao
    ) {
    }

    /**
     * Persist project-level metadata options.
     *
     * This is where, among other things, we put project options.
     *
     * Project options may need to be sanitized so that we can silently ignore impossible combinations,
     * and we can apply defaults when those are missing.
     *
     * @throws Exception
     */
    public function save(ProjectStructure $projectStructure, FeatureSet $features): void
    {
        $options = $projectStructure->metadata;

        // "From API" flag
        if ($projectStructure->from_api) {
            $options[ProjectsMetadataMarshaller::FROM_API->value] = '1';
        }

        // xliff_parameters — only persist when the model contains actual rules.
        // Guard with instanceof: createProject() normalizes to XliffRulesModel,
        // but saveMetadata() is protected and may be called from other paths.
        if (
            $projectStructure->xliff_parameters instanceof XliffRulesModel
            && (
                !empty($projectStructure->xliff_parameters->getRulesForVersion(1))
                || !empty($projectStructure->xliff_parameters->getRulesForVersion(2))
            )
        ) {
            $options[ProjectsMetadataMarshaller::XLIFF_PARAMETERS->value] = json_encode($projectStructure->xliff_parameters);
        }

        // pretranslate_101
        if (isset($projectStructure->pretranslate_101)) {
            $options[ProjectsMetadataMarshaller::PRE_TRANSLATE_101->value] = (string)$projectStructure->pretranslate_101;
        }

        // mt evaluation => ice_mt already in metadata
        // adds JSON parameters to the project metadata as JSON string
        if ($options[ProjectsMetadataMarshaller::MT_QE_WORKFLOW_ENABLED->value] ?? false) {
            $options[ProjectsMetadataMarshaller::MT_QE_WORKFLOW_PARAMETERS->value] = json_encode($options[ProjectsMetadataMarshaller::MT_QE_WORKFLOW_PARAMETERS->value]);
        } else {
            // When MT QE workflow is disabled, remove the raw array to prevent
            // passing a non-string value to MetadataDao::bulkSet()
            unset($options[ProjectsMetadataMarshaller::MT_QE_WORKFLOW_PARAMETERS->value]);
        }

        /**
         * Here we have the opportunity to add other features as dependencies of the ones
         * which are already explicitly set.
         */
        $features->loadProjectDependenciesFromProjectMetadata($options);

        // Store filters extraction parameters as JSON in project metadata if present
        if ($projectStructure->filters_extraction_parameters) {
            $options[ProjectsMetadataMarshaller::FILTERS_EXTRACTION_PARAMETERS->value] = json_encode($projectStructure->filters_extraction_parameters);
        }

        $extraKeys = [];
        // Collect all configuration parameter keys from every registered MT/TM engine
        foreach (EngineConstants::getAvailableEnginesList() as $engineName) {
            $extraKeys = array_merge(
                $extraKeys,
                (new $engineName(
                    new EngineStruct([
                        // MyMemory is a TM engine; all others are MT engines
                        'type' => $engineName == MyMemory::class ? EngineConstants::TM : EngineConstants::MT,
                    ])
                ))->getConfigurationParameters()
            );
        }

        // Copy any engine-specific config values (e.g., deepl_formality, mmt_glossaries)
        // from the project structure into the options array
        foreach ($extraKeys as $extraKey) {
            $engineValue = $projectStructure->$extraKey;
            if (!empty($engineValue)) {
                $options[$extraKey] = $engineValue;
            }
        }

        /** Duplicate the JobsMetadataMarshaller::SUBFILTERING_HANDLERS in project metadata for easier retrieval.
         * During the analysis of the project, there is no need to query the JobsMetadataDao.
         * Configuration about handlers can be changed later in the job settings.
         * But the analysis must everytime be performed with the current configuration.
         * @see JobCreationService::saveJobsMetadata()
         */
        if (!empty($projectStructure->subfiltering_handlers)) {
            $options[JobsMetadataMarshaller::SUBFILTERING_HANDLERS->value] = $projectStructure->subfiltering_handlers;
        }

        if (!empty($options)) {
            $stringOptions = array_map(static fn($value) => (string)$value, $options);
            $this->dao->bulkSet(
                (int)$projectStructure->id_project,
                $stringOptions
            );
        }
    }
}
