<?php

use API\V2\Validators\SegmentTranslationIssue;
use Features\AbstractRevisionFeature;
use Features\BaseFeature;
use Features\ISegmentTranslationModel;
use Features\ReviewExtended;
use Features\SecondPassReview\Model\ChunkReviewModel;
use Features\SecondPassReview\TranslationIssueModel;
use Klein\Request;
use LQA\ChunkReviewStruct;

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

    public function getChunkReviewModel( ChunkReviewStruct $chunkReviewStruct ) {
        if ( $this->_isSecondPass() ) {
            return new ChunkReviewModel( $chunkReviewStruct );
        } else {
            return $this->revision->getChunkReviewModel( $chunkReviewStruct );
        }
    }

    /**
     * @param SegmentTranslationChangeVector $translation
     * @param ChunkReviewStruct[]            $chunkReviews
     *
     * @return ISegmentTranslationModel
     */
    public function getSegmentTranslationModel( SegmentTranslationChangeVector $translation, array $chunkReviews ) {
        return $this->revision->getSegmentTranslationModel( $translation, $chunkReviews );
    }

    /**
     * This method use a filter because of external plugins
     *
     * @param Request $request
     *
     * @return mixed
     * @throws \Exception
     */
    public function getTranslationIssuesValidator( Request $request ) {
        return $this->_featureSet->filter( 'loadSegmentTranslationIssueValidator', new SegmentTranslationIssue( $request ) );
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
     * @return ReviewExtended\TranslationIssueModel|Features\SecondPassReview\TranslationIssueModel
     */
    public function getTranslationIssueModel( $id_job, $password, $issue ) {
        if ( $this->_isSecondPass() ) {
            return new TranslationIssueModel( $id_job, $password, $issue );
        } else {
            return $this->revision->getTranslationIssueModel( $id_job, $password, $issue );
        }
    }

    /**
     * This method is invoked to update the features before calling filters
     * @see self::getTranslationIssuesValidator
     * @param Projects_ProjectStruct $project
     *
     * @return static
     * @throws Exception
     */
    public static function initFromProject( Projects_ProjectStruct $project ) {
        foreach( $project->getFeatures() as $feature ){
            if( $feature instanceof AbstractRevisionFeature ){ //only one revision type can be present
                return static::getInstance( $feature )->setFeatureSet( $project->getFeatures() );
            }
        }
        /**
         * This return should never happens if the review_extended plugin is load as mandatory
         * When review_extended is not set as mandatory this factory should be never invoked
         * When review_improved is load by initProject
         */
        return static::getInstance(
                new ReviewExtended(
                        new BasicFeatureStruct( [ 'feature_code' => ReviewExtended::FEATURE_CODE ] )
                )
        )->setFeatureSet( $project->getFeatures() );
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