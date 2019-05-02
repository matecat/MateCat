<?php

use Exception;
use Features\TranslationVersions\Model\SegmentTranslationEventModel;
use Segments_SegmentDao;

class SegmentTranslationChangeVector {

    /**
     * @var Translations_SegmentTranslationStruct
     */
    private $translation;

    /**
     * @var Translations_SegmentTranslationStruct
     */
    private $old_translation;

    /**
     * @var Chunks_ChunkStruct
     */
    private $chunk;

    /**
     * @var SegmentTranslationEventModel
     */
    protected $eventModel ;

    public function __construct( SegmentTranslationEventModel $eventModel ) {
        $this->eventModel = $eventModel ;

        $this->translation     = $eventModel->getTranslation() ;
        $this->old_translation = $eventModel->getOldTranslation() ;
        $this->chunk           = $eventModel->getTranslation()->getChunk() ;
    }

    public function getPropagatedIds() {
        return $this->eventModel->getPropagatedIds() ;
    }

    public function didPropagate() {
        return count( $this->eventModel->getPropagatedIds() ) > 0 ;
    }

    /**
     * @return Translations_SegmentTranslationStruct
     */
    public function getTranslation() {
        return $this->translation;
    }

    public function getEventModel() {
        return $this->eventModel;
    }

    /**
     * @return Translations_SegmentTranslationStruct
     * @throws Exception
     */
    public function getOldTranslation() {
        if ( is_null( $this->old_translation ) ) {
            throw new Exception('Old translation is not set');
        }
        return $this->old_translation ;
    }

    public function getDestinationSourcePage() {
        return $this->eventModel->getCurrentEvent()->source_page ;
    }

    public function getOriginSourcePage() {
        return $this->eventModel->getPriorEvent()->source_page ;
    }

    public function isBeingUpperReviewed() {
        return $this->old_translation->isReviewedStatus() &&
        $this->translation->isReviewedStatus() &&
        $this->eventModel->isUpperRevision() ;
    }

    public function isBeingLowerReviewed() {
        return $this->old_translation->isReviewedStatus() &&
                $this->translation->isReviewedStatus() &&
                $this->eventModel->isLowerRevision() ;
    }

    /**
     * This method returns the list of source pages to invalidate in regards of reviewed words count.
     *
     * If a segment moves from R2 to R1 this returns [3]   ( = source page of R2 ).
     * If a segment moves from R1 to TR this returns [3,2] ( = source pages of R2 and R1 ).
     *
     * @return array
     */
    function getRollbackRevisionsSpan() {
        $source = $this->eventModel->getPriorEvent()->source_page ;
        $dest   = $this->eventModel->getCurrentEvent()->source_page ;
        $list   = [] ;

        while( $source > $dest && $source > Constants::SOURCE_PAGE_TRANSLATE ) {
            $list[] = $source-- ;
        }
        return $list ;
    }

    /**
     * @return bool
     */
    public function isEnteringReviewedState() {
        return (
                    $this->old_translation->isTranslationStatus() &&
                    $this->translation->isReviewedStatus() &&
                    ! $this->isUnmodifiedICE()
                )
                ||
                (
                    $this->old_translation->isReviewedStatus() &&
                    $this->translation->isReviewedStatus() &&
                    $this->isModifyingICE()
                );
    }

    protected function isModifyingICE() {
        return $this->old_translation->isICE() &&
                $this->old_translation->translation != $this->translation->translation &&
                $this->old_translation->version_number == 0 ;
    }

    /**
     * We need to know if the record is an umodified ICE
     * Unmodified ICEs are locked ICEs which have new version number equal to 0.
     *
     * @return bool
     */
    protected  function isUnmodifiedICE() {
        return $this->old_translation->isICE() &&               // segment is ICE
                $this->translation->version_number == 0 &&      // version number is not changing
                $this->old_translation->locked == 1             // in some cases ICEs are not locked. Only consider locked ICEs
                ;
    }

    /**
     * Exits reviewed state when it's not editing an ICE for the first time.
     * @return bool
     */
    public function isExitingReviewedState() {
        return $this->old_translation->isReviewedStatus() &&
                $this->translation->isTranslationStatus() &&
                ! $this->_isEditingICEforTheFirstTime() &&
                ! $this->_isChangingICEtoTranslatedWithNoChange() ;
    }

    protected function _isEditingICEforTheFirstTime() {
        return ( $this->old_translation->isICE() &&
                $this->old_translation->version_number == 0 &&
                $this->translation->version_number == 1
        );
    }

    protected function _isChangingICEtoTranslatedWithNoChange() {
        return $this->old_translation->isICE() &&
                $this->translation->isTranslationStatus() &&
                $this->old_translation->isReviewedStatus() &&
                $this->old_translation->version_number == $this->translation->version_number ;
    }


    /**
     * @return Segments_SegmentStruct
     */
    public function getSegmentStruct() {
        $dao = new Segments_SegmentDao( Database::obtain() );
        return $dao->getByChunkIdAndSegmentId(
            $this->chunk->id,
            $this->chunk->password,
            $this->translation->id_segment
        );
    }

}