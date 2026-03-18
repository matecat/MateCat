<?php

namespace Model\ProjectCreation;

use Exception;
use Model\Engines\Structs\EngineStruct;
use Model\FeaturesBase\FeatureSet;
use Model\Jobs\MetadataDao as JobsMetadataDao;
use Model\Projects\MetadataDao as ProjectsMetadataDao;
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
            $options[ProjectsMetadataDao::FROM_API] = '1';
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
            $options[ProjectsMetadataDao::XLIFF_PARAMETERS] = json_encode($projectStructure->xliff_parameters);
        }

        // pretranslate_101
        if (isset($projectStructure->pretranslate_101)) {
            $options[ProjectsMetadataDao::PRETRANSLATE_101] = (string)$projectStructure->pretranslate_101;
        }

        // mt evaluation => ice_mt already in metadata
        // adds JSON parameters to the project metadata as JSON string
        if ($options[ProjectsMetadataDao::MT_QE_WORKFLOW_ENABLED] ?? false) {
            $options[ProjectsMetadataDao::MT_QE_WORKFLOW_PARAMETERS] = json_encode($options[ProjectsMetadataDao::MT_QE_WORKFLOW_PARAMETERS]);
        } else {
            // When MT QE workflow is disabled, remove the raw array to prevent
            // passing a non-string value to MetadataDao::set()
            unset($options[ProjectsMetadataDao::MT_QE_WORKFLOW_PARAMETERS]);
        }

        /**
         * Here we have the opportunity to add other features as dependencies of the ones
         * which are already explicitly set.
         */
        $features->loadProjectDependenciesFromProjectMetadata($options);

        // Store filters extraction parameters as JSON in project metadata if present
        if ($projectStructure->filters_extraction_parameters) {
            $options[ProjectsMetadataDao::FILTERS_EXTRACTION_PARAMETERS] = json_encode($projectStructure->filters_extraction_parameters);
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

        // Persist all collected metadata options to the project_metadata table
        if (!empty($options)) {
            foreach ($options as $key => $value) {
                $this->dao->set(
                    (int)$projectStructure->id_project,
                    $key,
                    (string)$value
                );
            }
        }
        /** Duplicate the JobsMetadataDao::SUBFILTERING_HANDLERS in project metadata for easier retrieval.
         * During the analysis of the project, there is no need to query the JobsMetadataDao.
         * Configuration about handlers can be changed later in the job settings.
         * But the analysis must everytime be performed with the current configuration.
         * @see JobCreationService::saveJobsMetadata()
         */
        if (!empty($projectStructure->subfiltering_handlers)) {
            $this->dao->set(
                (int)$projectStructure->id_project,
                JobsMetadataDao::SUBFILTERING_HANDLERS,
                $projectStructure->subfiltering_handlers
            );
        }
    }
}
