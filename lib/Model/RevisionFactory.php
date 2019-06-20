<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 25/02/2019
 * Time: 15:55
 */

class RevisionFactory {

    /** @var  \Features\AbstractRevisionFeature */
    protected        $revision ;
    protected static $INSTANCE ;

    /**
     * @var FeatureSet
     */
    protected        $_featureSet;

    /**
     * @param \Features\BaseFeature|null $revisionFeature
     *
     * @return RevisionFactory
     * @throws Exception
     */
    public static function getInstance( $revisionFeature = null ) {
        if ( static::$INSTANCE == null && $revisionFeature == null ) {
            throw new Exception('Revision not defined');
        } elseif ( static::$INSTANCE == null ) {
            static::$INSTANCE = new self( $revisionFeature );
        }
        return static::$INSTANCE ;
    }

    protected function __construct( \Features\BaseFeature $revisionFeature ) {
        $this->revision = $revisionFeature ;
    }

    public function getChunkReviewModel( \LQA\ChunkReviewStruct $chunkReviewStruct ) {
        return $this->revision->getChunkReviewModel( $chunkReviewStruct );
    }

    public function getSegmentTranslationModel( SegmentTranslationChangeVector $translation ) {
        return $this->revision->getSegmentTranslationModel( $translation ) ;
    }

    /**
     * @param FeatureSet $featureSet
     *
     * @return $this
     */
    public function setFeatureSet( FeatureSet $featureSet ) {
        $this->_featureSet = $featureSet ;
        return $this ;
    }

    /**
     * @param $id_job
     * @param $password
     * @param $issue
     *
     * @return mixed
     */
    public function getTranslationIssueModel( $id_job, $password, $issue) {
        if ( in_array(Features::SECOND_PASS_REVIEW, $this->_featureSet->getCodes() ) ) {
            return new \Features\SecondPassReview\TranslationIssueModel($id_job, $password, $issue ) ;
        } else {
            return $this->revision->getTranslationIssueModel( $id_job, $password, $issue ) ;
        }
    }

    public static function initFromProject( Projects_ProjectStruct $project ) {
        $project->getFeatures();
        return static::getInstance() ;
    }

    /**
     * @return \Features\AbstractRevisionFeature|\Features\BaseFeature
     */
    public function getFeature() {
        return $this->revision ;
    }

}