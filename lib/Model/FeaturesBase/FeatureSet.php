<?php

namespace Model\FeaturesBase;

use Controller\Abstracts\IController;
use Controller\API\Commons\Exceptions\AuthenticationError;
use Controller\Views\TemplateDecorator\AbstractDecorator;
use Controller\Views\TemplateDecorator\Arguments\ArgumentInterface;
use Exception;
use Model\Exceptions\NotFoundException;
use Model\Exceptions\ValidationError;
use Model\FeaturesBase\Hook\FilterEvent;
use Model\FeaturesBase\Hook\RunEvent;
use Model\OwnerFeatures\OwnerFeatureDao;
use Model\Projects\ProjectsMetadataMarshaller;
use Model\Projects\ProjectStruct;
use PHPTAL;
use Plugins\Features\BaseFeature;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use Throwable;
use Utils\Logger\LoggerFactory;
use Utils\Registry\AppConfig;
use Utils\TaskRunner\Exceptions\EndQueueException;
use Utils\TaskRunner\Exceptions\ReQueueException;

/**
 * Class FeatureSet
 *
 * Represents a set of features provided in the system. This class allows the
 * management of features, including loading, merging, filtering, and various
 * dependency-related operations for projects and users.
 *
 * Implements EventDispatcherInterface.
 */
class FeatureSet implements EventDispatcherInterface
{
    /**
     * @var BasicFeatureStruct[]
     */
    private array $features = [];

    protected bool $_ignoreDependencies = false;

    private LoggerInterface $logger;

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
        $this->logger = LoggerFactory::getLogger('feature_set');

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
        $this->loadFromCodes($this->splitString($string));
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
        $featureCodes = (!empty($featureStrings)) ? $this->splitString($featureStrings) : [];

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
     * Load additional feature dependencies from project metadata.
     *
     * Note: The filterProjectDependencies hook was removed (no handler existed).
     * This method is kept as a public extension point — override in subclasses if needed.
     *
     * @param array<string, mixed> $_metadata
     */
    public function loadProjectDependenciesFromProjectMetadata(array $_metadata): void
    {
        if ($_metadata === []) {
            // no-op: filterProjectDependencies hook removed (zero handlers in all plugins)
        }

        // no-op: filterProjectDependencies hook removed (zero handlers in all plugins)
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
     * PSR-14 dispatch entry point.
     *
     * Routes to the appropriate internal dispatcher based on event type:
     * - FilterEvent/RunEvent: uses hookName() (no reflection), re-throws domain exceptions
     * - External events (subfiltering PSR-14): derives hook name from class name, swallows all exceptions
     *
     * @template T of object
     * @param T $event
     * @return T
     *
     * @throws EndQueueException
     * @throws AuthenticationError
     * @throws ReQueueException
     * @throws ValidationError
     * @throws NotFoundException
     */
    public function dispatch(object $event): object
    {
        if ($event instanceof FilterEvent || $event instanceof RunEvent) {
            $hookName = $event::hookName();
        } else {
            $shortName = (new ReflectionClass($event))->getShortName();
            $hookName  = lcfirst(str_replace('Event', '', $shortName));
        }

        $rethrowDomainExceptions = ($event instanceof FilterEvent || $event instanceof RunEvent);

        foreach ($this->features as $feature) {
            try {
                $obj = $feature->toNewObject();
                if (method_exists($obj, $hookName)) {
                    $obj->$hookName($event);
                }
            } catch (ValidationError|NotFoundException|AuthenticationError|ReQueueException|EndQueueException $e) {
                if ($rethrowDomainExceptions) {
                    throw $e;
                }
                $this->logger->error("Exception running hook " . $hookName . ": " . $e->getMessage());
            } catch (Throwable $e) {
                $this->logger->error("Exception running hook " . $hookName . ": " . $e->getMessage());
            }
        }

        return $event;
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

        $this->sortFeatures();
    }

    /**
     * @param string $string
     *
     * @return array<string>
     */
    public function splitString(string $string): array
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

}
