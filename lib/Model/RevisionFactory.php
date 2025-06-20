<?php

use AbstractControllers\KleinController;
use API\Commons\Exceptions\AuthenticationError;
use API\Commons\Validators\SegmentTranslationIssueValidator;
use Exceptions\NotFoundException;
use Exceptions\ValidationError;
use Features\AbstractRevisionFeature;
use Features\BaseFeature;
use Features\ReviewExtended;
use Features\ReviewExtended\ReviewedWordCountModel;
use Features\TranslationEvents\Model\TranslationEvent;
use LQA\ChunkReviewStruct;
use TaskRunner\Exceptions\EndQueueException;
use TaskRunner\Exceptions\ReQueueException;
use WordCount\CounterModel;

/**
 * Class RevisionFactory
 */
class RevisionFactory {

    /** @var  AbstractRevisionFeature */
    protected        $revision;
    protected static $INSTANCE;

    /**
     * @var FeatureSet
     */
    protected $_featureSet;

    /**
     * @param AbstractRevisionFeature $revisionFeature
     *
     * @return RevisionFactory
     * @throws Exception
     */
    public static function getInstance( AbstractRevisionFeature $revisionFeature = null ) {
        if ( static::$INSTANCE == null && $revisionFeature == null ) {
            throw new Exception( 'Revision not defined' );
        } elseif ( static::$INSTANCE == null ) {
            static::$INSTANCE = new self( $revisionFeature );
        }

        return static::$INSTANCE;
    }

    protected function __construct( BaseFeature $revisionFeature ) {
        $this->revision = $revisionFeature;
    }

    /**
     * @param ChunkReviewStruct $chunkReviewStruct
     *
     * @return ReviewExtended\IChunkReviewModel
     */
    public function getChunkReviewModel( ChunkReviewStruct $chunkReviewStruct ) {
        return $this->revision->getChunkReviewModel( $chunkReviewStruct );
    }

    /**
     * @param TranslationEvent    $translationEvent
     * @param ChunkReviewStruct[] $chunkReviews
     *
     * @return ReviewedWordCountModel
     */
    public function getReviewedWordCountModel( TranslationEvent $translationEvent, array $chunkReviews, CounterModel $jobWordCounter ) {
        return $this->revision->getReviewedWordCountModel( $translationEvent, $jobWordCounter, $chunkReviews );
    }

    /**
     * This method uses a filter because of external plugins
     *
     * @param KleinController $controller
     *
     * @return SegmentTranslationIssueValidator
     * @throws AuthenticationError
     * @throws NotFoundException
     * @throws ValidationError
     * @throws EndQueueException
     * @throws ReQueueException
     */
    public function getTranslationIssuesValidator( KleinController $controller ): SegmentTranslationIssueValidator {
        return $this->_featureSet->filter( 'loadSegmentTranslationIssueValidator', new SegmentTranslationIssueValidator( $controller ) );
    }

    /**
     * @param FeatureSet $featureSet
     *
     * @return $this
     */
    public function setFeatureSet( FeatureSet $featureSet ) {
        $this->_featureSet = $featureSet;

        return $this;
    }

    /**
     * @param $id_job
     * @param $password
     * @param $issue
     *
     * @return ReviewExtended\TranslationIssueModel
     */
    public function getTranslationIssueModel( $id_job, $password, $issue ) {
        return $this->revision->getTranslationIssueModel( $id_job, $password, $issue );
    }

    /**
     * IMPORTANT
     *
     * This method is invoked to update/reset the features before invoking filter callbacks.
     * This is needed because, by default, 'mandatory_plugins' section, in the configuration ini file,
     * always load 'review_extended' or 'second_pass_review' ( which has 'review_extended' as dependency ) when the application bootstraps.
     *
     * If the old 'review_improved' feature is enabled for the project, a singleton instance every time would returns 'review_extended'
     *
     * This works because revision plugins are by default not forcedly injected on projects ( $forceOnProject == false ).
     *
     * @param Projects_ProjectStruct $project
     *
     * @return static
     * @throws Exception
     * @see RevisionFactory::getTranslationIssuesValidator
     */
    public static function initFromProject( Projects_ProjectStruct $project ) {
        foreach ( $project->getFeaturesSet()->getFeaturesStructs() as $featureStruct ) {
            $feature = $featureStruct->toNewObject();
            if ( $feature instanceof AbstractRevisionFeature ) { //only one revision type can be present
                return static::getInstance( $feature )->setFeatureSet( $project->getFeaturesSet() );
            }
        }

        /**
         * This return should never happens if the review_extended plugin is load as mandatory
         * When the OLD revision is set, this factory should be never invoked
         * When review_improved or review_extended is loaded by initProject we never reach this line
         */
        return static::getInstance(
                new ReviewExtended(
                        new BasicFeatureStruct( [ 'feature_code' => ReviewExtended::FEATURE_CODE ] )
                )
        )->setFeatureSet( $project->getFeaturesSet() );
    }

    /**
     * @return AbstractRevisionFeature
     */
    public function getRevisionFeature() {
        return $this->revision;
    }

    protected function _isSecondPass() {
        return in_array( Features::SECOND_PASS_REVIEW, $this->_featureSet->getCodes() );
    }

}