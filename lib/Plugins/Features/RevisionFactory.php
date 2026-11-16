<?php

namespace Plugins\Features;

use Exception;
use Model\DataAccess\IDatabase;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\FeatureSet;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectStruct;

/**
 * Class RevisionFactory
 */
class RevisionFactory
{

    protected AbstractRevisionFeature $revision;
    protected static ?RevisionFactory $INSTANCE = null;

    /**
     * @var FeatureSet
     */
    protected FeatureSet $_featureSet;

    /**
     * @param AbstractRevisionFeature|null $revisionFeature
     *
     * @return self
     * @throws Exception
     */
    public static function getInstance(?AbstractRevisionFeature $revisionFeature = null): self
    {
        if (self::$INSTANCE === null) {
            if ($revisionFeature === null) {
                throw new Exception('Revision not defined');
            }
            self::$INSTANCE = new self($revisionFeature);
        }

        return self::$INSTANCE;
    }

    protected function __construct(AbstractRevisionFeature $revisionFeature)
    {
        $this->revision = $revisionFeature;
    }

    /**
     * @param ChunkReviewStruct $chunkReviewStruct
     *
     * @return ReviewExtended\IChunkReviewModel
     */
    public function getChunkReviewModel(ChunkReviewStruct $chunkReviewStruct): ReviewExtended\IChunkReviewModel
    {
        return $this->revision->getChunkReviewModel($chunkReviewStruct);
    }

    /**
     * @param FeatureSet $featureSet
     *
     * @return $this
     */
    public function setFeatureSet(FeatureSet $featureSet): self
    {
        $this->_featureSet = $featureSet;

        return $this;
    }

    /**
     * IMPORTANT
     *
     * This method is invoked to update/reset the features before invoking filter callbacks.
     * This is needed because, by default, the AUTOLOAD_PLUGINS setting in the configuration ini file
     * always loads 'second_pass_review' when the application bootstraps.
     *
     * This works because revision plugins are by default not forcedly injected on projects ($forceOnProject == false).
     *
     * @param ProjectStruct $project
     * @param IDatabase $database
     *
     * @return self
     * @throws Exception
     */
    public static function initFromProject(ProjectStruct $project, IDatabase $database): self
    {
        $featureSet = FeatureSet::forProject($project, $database);

        foreach ($featureSet->getFeaturesStructs() as $featureStruct) {
            $feature = $featureStruct->toNewObject($database);
            if ($feature instanceof AbstractRevisionFeature) { //only one revision type can be present
                return self::getInstance($feature)->setFeatureSet($featureSet);
            }
        }

        /**
         * This return should never happen while the second_pass_review plugin is in AUTOLOAD_PLUGINS
         */
        return self::getInstance(
            new SecondPassReview(new BasicFeatureStruct(['feature_code' => SecondPassReview::FEATURE_CODE]))
        )->setFeatureSet($featureSet);
    }

    /**
     * @return AbstractRevisionFeature
     */
    public function getRevisionFeature(): AbstractRevisionFeature
    {
        return $this->revision;
    }

}