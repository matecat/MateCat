<?php

use API\V2\Validators\SegmentTranslationIssue;
use Features\AbstractRevisionFeature;
use Features\BaseFeature;
use Features\SecondPassReview\Model\ChunkReviewModel;
use Klein\Request;
use LQA\ChunkReviewStruct;

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 25/02/2019
 * Time: 15:55
 */
class RevisionFactory {

    /** @var  \Features\AbstractRevisionFeature */
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
     * @return \Features\ISegmentTranslationModel
     */
    public function getSegmentTranslationModel( SegmentTranslationChangeVector $translation, array $chunkReviews ) {
        return $this->revision->getSegmentTranslationModel( $translation, $chunkReviews );
    }

    /**
     * @param \Klein\Request $request
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
     * @return mixed
     */
    public function getTranslationIssueModel( $id_job, $password, $issue ) {
        if ( $this->_isSecondPass() ) {
            return new \Features\SecondPassReview\TranslationIssueModel( $id_job, $password, $issue );
        } else {
            return $this->revision->getTranslationIssueModel( $id_job, $password, $issue );
        }
    }

    public static function initFromProject( Projects_ProjectStruct $project ) {
        return static::getInstance()->setFeatureSet( $project->getFeatures() );
    }

    /**
     * @return \Features\AbstractRevisionFeature|\Features\BaseFeature
     */
    public function getFeature() {
        return $this->revision;
    }

    protected function _isSecondPass() {
        return in_array( Features::SECOND_PASS_REVIEW, $this->_featureSet->getCodes() );
    }

}