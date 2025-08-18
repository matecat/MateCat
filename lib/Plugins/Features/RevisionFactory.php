<?php

namespace Plugins\Features;

use Exception;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\FeatureSet;
use Model\LQA\ChunkReviewStruct;
use Model\Projects\ProjectStruct;

/**
 * Class RevisionFactory
 */
class RevisionFactory {

    protected AbstractRevisionFeature $revision;
    protected static ?RevisionFactory $INSTANCE = null;

    /**
     * @var FeatureSet
     */
    protected FeatureSet $_featureSet;

    /**
     * @param AbstractRevisionFeature|null $revisionFeature
     *
     * @return RevisionFactory
     * @throws Exception
     */
    public static function getInstance( AbstractRevisionFeature $revisionFeature = null ): RevisionFactory {
        if ( static::$INSTANCE == null && $revisionFeature == null ) {
            throw new Exception( 'Revision not defined' );
        } elseif ( static::$INSTANCE == null ) {
            static::$INSTANCE = new self( $revisionFeature );
        }

        return static::$INSTANCE;
    }

    protected function __construct( AbstractRevisionFeature $revisionFeature ) {
        $this->revision = $revisionFeature;
    }

    /**
     * @param ChunkReviewStruct $chunkReviewStruct
     *
     * @return ReviewExtended\IChunkReviewModel
     */
    public function getChunkReviewModel( ChunkReviewStruct $chunkReviewStruct ): ReviewExtended\IChunkReviewModel {
        return $this->revision->getChunkReviewModel( $chunkReviewStruct );
    }

    /**
     * @param FeatureSet $featureSet
     *
     * @return $this
     */
    public function setFeatureSet( FeatureSet $featureSet ): RevisionFactory {
        $this->_featureSet = $featureSet;

        return $this;
    }

    /**
     * IMPORTANT
     *
     * This method is invoked to update/reset the features before invoking filter callbacks.
     * This is needed because, by default, 'mandatory_plugins' section, in the configuration ini file,
     * always load 'review_extended' or 'second_pass_review' (which has 'review_extended' as dependency) when the application bootstraps.
     *
     * If the old 'review_improved' feature is enabled for the project, a singleton instance every time would return 'review_extended'
     *
     * This works because revision plugins are by default not forcedly injected on projects ($forceOnProject == false).
     *
     * @param ProjectStruct $project
     *
     * @return static
     * @throws Exception
     */
    public static function initFromProject( ProjectStruct $project ): RevisionFactory {
        foreach ( $project->getFeaturesSet()->getFeaturesStructs() as $featureStruct ) {
            $feature = $featureStruct->toNewObject();
            if ( $feature instanceof AbstractRevisionFeature ) { //only one revision type can be present
                return static::getInstance( $feature )->setFeatureSet( $project->getFeaturesSet() );
            }
        }

        /**
         * This return should never happen if the review_extended plugin is loaded as mandatory (or as dependency of mandatory second_pass_review plugin)
         */
        return static::getInstance(
                new SecondPassReview( new BasicFeatureStruct( [ 'feature_code' => ReviewExtended::FEATURE_CODE ] ) )
        )->setFeatureSet( $project->getFeaturesSet() );
    }

    /**
     * @return AbstractRevisionFeature
     */
    public function getRevisionFeature(): AbstractRevisionFeature {
        return $this->revision;
    }

}