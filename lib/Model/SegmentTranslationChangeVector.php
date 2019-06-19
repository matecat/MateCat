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

    public function getPropagatedEvents() {
        return $this->eventModel->getPropagatedEvents();
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
     * @return Users_UserStruct
     */
    public function getEventUser() {
        if ( $this->eventModel->getCurrentEvent()->uid ) {
            return ( new Users_UserDao())->getByUid( $this->eventModel->getCurrentEvent()->uid ) ;
        }
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

    /**
     * Origin source page can be missing. This can happen in case of ICE matches, or pre-transalted
     * segments or just because we are evaluating a transition from NEW to TRANSLATED status.
     *
     * In such case we need to make assumptions on the `source_page` variable because that's what we use
     * to decide where to move revised words and advancement words around.
     * @return mixed
     * @throws \Exception
     */
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
     * Returns 1 if source page is moving up  0 if it's not changing, -1 if it's moving down.
     *
     * @return int
     */
    public function getSourcePageDirection() {
        $originSourcePage      = $this->eventModel->getOriginSourcePage() ;
        $destinationSourcePage = $this->eventModel->getDestinationSourcePage() ;

        return $originSourcePage < $destinationSourcePage ? 1  : (
            $originSourcePage == $destinationSourcePage ? null : -1
        );
    }

    /**
     *
     * This method returns the list of revision source pages codes that which are involved in the current
     * transition. Source page for Translate page is skipped.
     * Example:
     *
     * - if moving from R1 to R2 => returns [2,3]
     * - if moving frmo R4 to R1 => returns [5,4,3,2]
     * - the change remains on R3 => returns [4]
     *
     * @return array
     */
    public function sourcePagesSpan() {
        $min = $this->eventModel->getOriginSourcePage() ;
        $max = $this->eventModel->getDestinationSourcePage() ;

        if ( $this->getSourcePageDirection() === -1 ) {
            $min = $this->eventModel->getDestinationSourcePage() ;
            $max = $this->eventModel->getOriginSourcePage() ;
        }

        $list   = [] ;

        while( $max >= $min ) {
            $list[] = $max-- ;
        }

        return array_filter( $list, function($i) {
            return $i != Constants::SOURCE_PAGE_TRANSLATE ;
        });
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

    public function isModifyingICE() {
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
     * Equivalent word count is the same on both segment translation structs (new and old).
     * The new translation struct lacks of this information because it's not populated by a database query.
     * So we look for this count on the old translation.
     *
     * In case of ICE match, this method returns raw_words_count in order to be compatible with job stats.
     */
    public function getRawOrEquivalentWordsCount() {
        if ( $this->old_translation->isICE() ) {
            return $this->getSegmentStruct()->raw_word_count ;
        }
        else {
            return $this->old_translation->eq_word_count ;
        }
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