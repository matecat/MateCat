<?php

namespace Model\FeaturesBase;

use Controller\Abstracts\IController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\Features\ProjectCompletion\CompletionEventStruct;
use Controller\Views\TemplateDecorator\AbstractDecorator;
use Controller\Views\TemplateDecorator\Arguments\ArgumentInterface;
use Exception;
use Matecat\SubFiltering\Contracts\FeatureSetInterface;
use Model\ChunksCompletion\ChunkCompletionEventStruct;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\Jobs\JobStruct;
use Model\JobSplitMerge\SplitMergeProjectData;
use Model\LQA\ChunkReviewStruct;
use Model\OwnerFeatures\OwnerFeatureDao;
use Model\ProjectCreation\ProjectStructure;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Projects\ProjectStruct;
use PHPTAL;
use Plugins\Features\BaseFeature;
use ReflectionException;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;

/**
 * FeatureSet — WordPress-style plugin hook dispatcher.
 *
 * Dispatches hooks via {@see filter()} (data transformation) and {@see run()} (side effects).
 * Plugins implement handlers by defining methods matching the hook name on their BaseFeature subclass.
 *
 * --- Filter hooks (return the filtered value) ---
 *
 * @method string  isAnInternalUser(string $email)
 * @method array<string, mixed>   outsourceAvailableInfo(string $targetLang, string $idCustomer, int $idJob)
 * @method mixed   projectUrls(mixed $formatted)
 * @method array<string, BasicFeatureStruct>   filterCreateProjectFeatures(array<string, BasicFeatureStruct> $projectFeatures)
 * @method mixed   encodeInstructions(mixed $value)
 * @method mixed   decodeInstructions(mixed $value)
 * @method array<string, mixed>   filterActivityLogEntry(array<string, mixed> $record)
 * @method array<string, mixed>   filterCreationStatus(array<string, mixed> $result, ProjectStruct $project)
 * @method string  overrideConversionResult(string $content)
 * @method array<string, mixed>   filterGlobalWarnings(array<string, mixed> $result, array<string, mixed> $context)
 * @method array<string, mixed>   filterSegmentWarnings(array<string, mixed> $data, array<string, mixed> $context)
 * @method array<string, mixed>   filterSetTranslationResult(array<string, mixed> $result, array<string, mixed> $context)
 * @method mixed   filterContributionStructOnSetTranslation(mixed $contributionStruct, ProjectStruct $project, mixed $segment)
 * @method mixed   filterContributionStructOnMTSet(mixed $contributionStruct, mixed $translation, mixed $segment, mixed $filter)
 * @method array<string, mixed>   filterGetSegmentsResult(array<string, mixed> $data, JobStruct $chunk)
 * @method array<int, mixed>   prepareNotesForRendering(array<int, mixed> $notes)
 * @method bool    prepareAllNotes(bool $default)
 * @method array<int, mixed>   processExtractedJsonNotes(array<int, mixed> $segmentNotes)
 * @method bool    filterIsChunkCompletionUndoable(bool $undoable, ProjectStruct $project, JobStruct $chunk)
 * @method string  filter_job_password_to_review_password(string $password, int $idJob)
 * @method array<int, string>   filterRevisionChangeNotificationList(array<int, string> $emails)
 * @method array<string, mixed>   filterMyMemoryGetParameters(array<string, mixed> $parameters, array<string, mixed> $config)
 * @method mixed   characterLengthCount(string $cleanedString)
 * @method array<int, string>   injectExcludedTagsInQa(array<int, string> $excludedTags)
 * @method int     checkTagMismatch(int $errorCode, mixed $qaInstance)
 * @method int     checkTagPositions(int $errorCode, mixed $qaInstance)
 * @method array<string, mixed>   analysisBeforeMTGetContribution(array<string, mixed> $config, mixed $mtEngine, mixed $queueElement)
 * @method array<string, mixed>   filterPayableRates(array<string, mixed> $rates, string $sourceLanguage, string $targetLanguage)
 * @method mixed   wordCount(mixed $wordCount)
 * @method bool    populatePreTranslations(bool $default)
 * @method bool    doNotManageAlternativeTranslations(bool $default, array<string, mixed> $xliffTransUnit, array<string, mixed> $xliffFileAttributes)
 * @method array<string, mixed>   sanitizeOriginalDataMap(array<string, mixed> $originalDataMap)
 * @method string  correctTagErrors(string $segment, array<string, mixed> $originalDataMap)
 * @method array<string, mixed>   appendFieldToAnalysisObject(array<string, mixed> $metadata, ProjectStructure $projectStructure)
 * @method array<string, mixed>   filter_team_for_project_creation(array<string, mixed> $teamData)
 * @method ProjectStructure handleJsonNotesBeforeInsert(ProjectStructure $projectStructure)
 * @method array<int, string>   filterProjectDependencies(array<int, string> $projectDependencies, array<string, mixed> $metadata)
 * @method BasicFeatureStruct[] filterFeaturesMerged(BasicFeatureStruct[] $features)
 * @method mixed   rewriteContributionContexts(mixed $segmentsList, array<string, mixed> $requestData)
 * @method array<int|string, mixed>   appendInitialTemplateVars(array<int|string, mixed> $codes)
 *
 * --- Run hooks (void, side effects only) ---
 *
 * @method void setTranslationCommitted(array<string, mixed> $context)
 * @method void postAddSegmentTranslation(array<string, mixed> $context)
 * @method void chunkReviewUpdated(ChunkReviewStruct $chunkReview, mixed $updateResult, mixed $model, ProjectStruct $project)
 * @method void job_password_changed(JobStruct $job, string $oldPassword)
 * @method void review_password_changed(int $jobId, string $oldPassword, string $newPassword, int $revisionNumber)
 * @method void project_password_changed(ProjectStruct $project, string $oldPassword)
 * @method void project_completion_event_saved(JobStruct $chunk, CompletionEventStruct $event, int $completionEventId)
 * @method void processZIPDownloadPreview(mixed $controller, array<int, mixed> $outputContent)
 * @method void checkSplitAccess(array<int, JobStruct> $jobList)
 * @method void afterTMAnalysisCloseProject(int $projectId, array<string, mixed> $analyzedReport)
 * @method void tmAnalysisDisabled(int $projectId)
 * @method void fastAnalysisComplete(array<int, mixed> $segments, array<string, mixed> $projectRow)
 * @method void bootstrapCompleted()
 * @method void postJobSplitted(SplitMergeProjectData $data)
 * @method void postJobMerged(SplitMergeProjectData $data, JobStruct $chunk)
 * @method void validateJobCreation(JobStruct $job, ProjectStructure $projectStructure)
 * @method void validateProjectCreation(ProjectStructure $projectStructure)
 * @method void beforeProjectCreation(ProjectStructure $projectStructure, array<string, mixed> $context)
 * @method void postProjectCreate(ProjectStructure $projectStructure)
 * @method void postProjectCommit(ProjectStructure $projectStructure)
 * @method void filterProjectNameModified(int $idProject, string $name, string $password, string $ownerEmail)
 * @method void handleTUContextGroups(ProjectStructure $projectStructure)
 * @method void alter_chunk_review_struct(ChunkCompletionEventStruct $event)
 *
 * @see BaseFeature — Plugins implement these hooks as methods
 * @see \Plugins\Features\AbstractRevisionFeature — Example handler implementations
 */
class FeatureSet implements FeatureSetInterface
{
    /**
     * @var BasicFeatureStruct[]
     */
    private array $features = [];

    protected bool $_ignoreDependencies = false;

    /**
     * @return BasicFeatureStruct[]
     */
    public function getFeaturesStructs(): array
    {
        return $this->features;
    }

    /**
     * Initializes a new FeatureSet. If $features param is provided, FeaturesSet is populated with the given params.
     * Otherwise, it is populated with mandatory features.
     *
     * @param BasicFeatureStruct[]|null $features
     *
     * @throws Exception
     */
    public function __construct(?array $features = null)
    {
        if (is_null($features)) {
            $this->loadFromMandatory();
        } else {
            $_features = [];
            foreach ($features as $feature) {
                $_features[$feature->feature_code] = $feature;
            }
            $this->merge($_features);
        }
    }

    /**
     * @return array<string>
     */
    public function getCodes(): array
    {
        return array_values(array_map(function (BasicFeatureStruct $feature): string {
            return $feature->feature_code;
        }, $this->features));
    }

    /**
     * @param string $string
     *
     * @throws Exception
     */
    public function loadFromString(string $string): void
    {
        $this->loadFromCodes(FeatureSet::splitString($string));
    }

    /**
     * @param string[]|null $feature_codes
     *
     * @throws Exception
     */
    private function loadFromCodes(?array $feature_codes = []): void
    {
        $features = [];

        if (!empty($feature_codes)) {
            foreach ($feature_codes as $code) {
                $features [$code] = new BasicFeatureStruct(['feature_code' => $code]);
            }

            $this->merge($features);
        }
    }

    /**
     * Reset all existing features and load the mandatory ones.
     * Load features that should be enabled on project scope.
     *
     * Those features include:
     *
     * 1. The ones explicitly defined `project_metadata`;
     * 2. The ones in the autoloaded array that can be forcedly enabled on a project.
     *
     * @param ProjectStruct $project
     *
     * @return void
     * @throws Exception
     */
    public function loadForProject(ProjectStruct $project): void
    {
        $featureStrings = $project->getMetadataValue(ProjectsMetadataMarshaller::FEATURES_KEY->value);
        $featureCodes = (!empty($featureStrings)) ? FeatureSet::splitString($featureStrings) : [];

        $this->clear();
        $this->_setIgnoreDependencies(true);
        $this->loadForceableProjectFeatures();
        $this->loadFromCodes($featureCodes);
        $this->_setIgnoreDependencies(false);
    }

    protected function _setIgnoreDependencies(bool $value): void
    {
        $this->_ignoreDependencies = $value;
    }

    public function clear(): void
    {
        $this->features = [];
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @throws AuthenticationError
     * @throws EndQueueException
     * @throws NotFoundException
     * @throws ReQueueException
     * @throws ValidationError
     * @throws Exception
     */
    public function loadProjectDependenciesFromProjectMetadata(array $metadata): void
    {
        $project_dependencies = [];
        $project_dependencies = $this->filter('filterProjectDependencies', $project_dependencies, $metadata);
        $features = [];
        foreach ($project_dependencies as $dependency) {
            $features [$dependency] = new BasicFeatureStruct(['feature_code' => $dependency]);
        }

        $this->merge($features);
    }

    /**
     * Loads features associated with a user based on their email.
     *
     * This method retrieves features linked to the specified customer ID,
     * clears the current feature set, loads mandatory features, and merges
     * the retrieved features into the feature set.
     *
     * @param string $id_customer The ID of the customer whose features are to be loaded.
     *
     * @return void
     * @throws Exception If an error occurs during the merging process.
     */
    public function loadFromUserEmail(string $id_customer): void
    {
        $features = OwnerFeatureDao::getByIdCustomer($id_customer);
        $this->clear();
        $this->_setIgnoreDependencies(false);
        $this->loadFromMandatory();
        $this->merge($features);
    }

    /**
     * Loads features that can be forced on projects, even if they are not assigned to project explicitly,
     * reading from AUTOLOAD_PLUGINS.
     *
     * @throws Exception
     */
    public function loadForceableProjectFeatures(): void
    {
        $returnable = array_filter($this->getAutoloadPlugins(), function (BasicFeatureStruct $feature) {
            $concreteClass = $feature->toNewObject();

            return $concreteClass->isForceableOnProject();
        });

        $this->merge($returnable);
    }

    /**
     * Loads features that can be activated automatically on proejct, i.e. those that
     * don't require a parameter to be passed from the UI.
     *
     * This functions does some transformation to leverage `autoActivateOnProject()` function
     * which is defined on the concrete feature class.
     *
     * So it does the following:
     *
     * 1. Find all owner_features for the given user
     * 2. Instantiate a concrete feature class for each record
     * 3. Filter the list based on the return of autoActivateOnProject()
     * 4. Populate the featureSet with the resulting OwnerFeatureStruct
     *
     * @param string $id_customer
     *
     * @throws ReflectionException
     * @throws Exception
     */
    public function loadAutoActivableOwnerFeatures(string $id_customer): void
    {
        $features = OwnerFeatureDao::getByIdCustomer($id_customer);

        $objs = array_map(function (BasicFeatureStruct $feature): BaseFeature {
            return $feature->toNewObject();
        }, $features);

        $returnable = array_filter($objs, function (BaseFeature $obj): bool {
            return $obj->isAutoActivableOnProject();
        });

        $this->merge(array_map(function (BaseFeature $feature): BasicFeatureStruct {
            return $feature->getFeatureStruct();
        }, $returnable));
    }

    /**
     * Returns the filtered subject variable passed to all enabled features.
     *
     * @param string $method
     * @param mixed $filterable
     *
     * @return mixed
     *
     * @throws NotFoundException
     * @throws ValidationError
     * @throws AuthenticationError
     * @throws ReQueueException
     * @throws EndQueueException
     */
    public function filter(string $method, mixed $filterable): mixed
    {
        $args = array_slice(func_get_args(), 1);

        foreach ($this->features as $feature) {
            $obj = $feature->toNewObject();

            if (method_exists($obj, $method)) {
                array_shift($args);
                array_unshift($args, $filterable);

                try {
                    /**
                     * There may be the need to avoid a filter to be executed before or after other ones.
                     * To solve this problem, we could always pass the last argument to call_user_func_array which
                     * contains a list of executed feature codes.
                     *
                     * Example: $args + [ $executed_features ]
                     *
                     * This way plugins have the chance to decide whether to change the value, throw an exception or
                     * do whatever they need to based on the behaviour of the other features.
                     *
                     */
                    $filterable = $obj->$method(...$args);
                } catch (ValidationError|NotFoundException|AuthenticationError|ReQueueException|EndQueueException $e) {
                    throw $e;
                } catch (Exception $e) {
                    LoggerFactory::getLogger('feature_set')->error("Exception running filter " . $method . ": " . $e->getMessage());
                }
            }
        }

        return $filterable;
    }


    /**
     * @param string $method
     */
    public function run(string $method): void
    {
        $args = array_slice(func_get_args(), 1);
        foreach ($this->features as $feature) {
            $this->runOnFeature($method, $feature, $args);
        }
    }

    /**
     * appendDecorators
     *
     * Loads feature specific decorators, if any is found.
     *
     * Also, gives a last chance to plugins to define a custom decorator class to be
     * added to any call.
     *
     * @param string $name name of the decorator to activate
     * @param IController $controller the controller to work on
     * @param PHPTAL $template the PHPTAL view to add properties to
     *
     * @throws Exception
     */
    public function appendDecorators(string $name, IController $controller, PHPTAL $template, ?ArgumentInterface $arguments = null): void
    {
        foreach ($this->features as $feature) {
            $cls = PluginsLoader::getFeatureClassDecorator($feature, $name);
            if (!empty($cls)) {
                /** @var AbstractDecorator $obj */
                $obj = new $cls($controller, $template);
                $obj->decorate($arguments);
            }
        }
    }

    /**
     * This function ensures that whenever a plugin load is requested,
     * its own dependencies are also loaded
     *
     * These dependencies are ordered so the plugin is every time at the last position
     *
     * @throws Exception
     */
    public function sortFeatures(): FeatureSet
    {
        $toBeSorted = array_values($this->features);
        $sortedFeatures = $this->quickSort($toBeSorted);

        $this->clear();
        foreach ($sortedFeatures as $value) {
            $this->features[$value->feature_code] = $value;
        }

        return $this;
    }

    /**
     * Warning Recursion, memory overflow if there are a lot of features ( but this is impossible )
     *
     * @param BasicFeatureStruct[] $featureStructsList
     *
     * @return BasicFeatureStruct[]
     */
    private function quickSort(array $featureStructsList): array
    {
        $length = count($featureStructsList);
        if ($length < 2) {
            return $featureStructsList;
        }

        $firstInList = $featureStructsList[0];
        $ObjectFeatureFirst = $firstInList->toNewObject();

        $leftBucket = $rightBucket = [];

        for ($i = 1; $i < $length; $i++) {
            if (in_array($featureStructsList[$i]->feature_code, $ObjectFeatureFirst::getDependencies())) {
                $leftBucket[] = $featureStructsList[$i];
            } else {
                $rightBucket[] = $featureStructsList[$i];
            }
        }

        return array_merge($this->quickSort($leftBucket), [$firstInList], $this->quickSort($rightBucket));
    }

    /**
     * Foe each feature Load it's defined dependencies
     * @throws Exception
     */
    private function loadFeatureDependencies(): void
    {
        $codes = $this->getCodes();
        foreach ($this->features as $feature) {
            $baseFeature = $feature->toNewObject();
            $missing_dependencies = array_diff($baseFeature::getDependencies(), $codes);

            if (!empty($missing_dependencies)) {
                foreach ($missing_dependencies as $code) {
                    $this->features [$code] = new BasicFeatureStruct(['feature_code' => $code]);
                }
            }
        }
    }

    /**
     * Updates the PluginsLoader array with new features. Ensures no duplicates are created.
     * Loads dependencies as needed.
     *
     * @param array<string, BasicFeatureStruct> $new_features
     *
     * @throws Exception
     */
    private function merge(array $new_features): void
    {
        if (!$this->_ignoreDependencies) {
            $this->loadFeatureDependencies();
        }

        $all_features = [];
        $conflictingDeps = [];

        foreach ($new_features as $feature) {
            // flat dependency management
            $baseFeature = $feature->toNewObject();

            $conflictingDeps[$feature->feature_code] = $baseFeature::getConflictingDependencies();

            $deps = [];

            if (!$this->_ignoreDependencies) {
                $deps = array_map(function ($code) {
                    return new BasicFeatureStruct(['feature_code' => $code]);
                }, $baseFeature->getDependencies());
            }

            $all_features = array_merge($all_features, $deps, [$feature]);
        }

        /** @var BasicFeatureStruct $feature */
        foreach ($all_features as $feature) {
            foreach ($conflictingDeps as $key => $value) {
                if (empty($value)) {
                    continue;
                }
                if (in_array($feature->feature_code, $value)) {
                    throw new Exception("$feature->feature_code is conflicting with $key.");
                }
            }
            if (!isset($this->features[$feature->feature_code])) {
                $this->features[$feature->feature_code] = $feature;
            }
        }

        $this->features = $this->filter('filterFeaturesMerged', $this->features);
        $this->sortFeatures();
    }

    /**
     * @param string $string
     *
     * @return array<string>
     */
    public static function splitString(string $string): array
    {
        return array_filter(explode(',', trim($string)));
    }

    /**
     * Loads plugins into the FeatureSet from the list of mandatory plugins.
     *
     * @return void
     *
     * @throws Exception
     */
    private function loadFromMandatory(): void
    {
        $features = $this->getAutoloadPlugins();
        $this->merge($features);
    }

    /**
     * @return array<string, BasicFeatureStruct>
     */
    private function getAutoloadPlugins(): array
    {
        $features = [];

        if (!empty(AppConfig::$AUTOLOAD_PLUGINS)) {
            foreach (AppConfig::$AUTOLOAD_PLUGINS as $plugin) {
                $features[$plugin] = new BasicFeatureStruct(['feature_code' => $plugin]);
            }
        }

        return $features;
    }

    /**
     * Runs a command on a single feature
     *
     * @param string $method
     * @param BasicFeatureStruct $feature
     * @param array<int, mixed> $args
     *
     * @return void
     */
    private function runOnFeature(string $method, BasicFeatureStruct $feature, array $args): void
    {
        $name = PluginsLoader::getPluginClass($feature->feature_code);
        if ($name) {
            $obj = new $name($feature);

            if (method_exists($obj, $method)) {
                $obj->$method(...$args);
            }
        }
    }

}