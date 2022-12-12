<?php

namespace Features\ReviewExtended;

use Features\TranslationVersions\Model\TranslationEvent;
use LQA\ChunkReviewStruct;
use LQA\EntryWithCategoryStruct;

class ChunkReviewTranslationEventTransition {

    /**
     * @var TranslationEvent
     */
    private $translationEvent;

    /**
     * @var array
     */
    private $unsetFinalRevision;

    /**
     * @var ChunkReviewStruct[]
     */
    private $chunk_reviews = [];

    /**
     * @var EntryWithCategoryStruct[]
     */
    private $issues_to_delete = [];

    /**
     * ChunkReviewTransitionDao_ChunkReviewTransitionModel constructor.
     *
     * @param TranslationEvent $changeVector
     */
    public function __construct( TranslationEvent $changeVector ) {
        $this->translationEvent = $changeVector;
    }

    /**
     * @return TranslationEvent
     */
    public function getTranslationEvent() {
        return $this->translationEvent;
    }

    /**
     * @return array
     */
    public function getUnsetFinalRevision() {
        return $this->unsetFinalRevision;
    }

    /**
     * @param integer[] $unsetFinalRevision
     */
    public function unsetFinalRevision( $unsetFinalRevision ) {
        $this->unsetFinalRevision = $unsetFinalRevision;
    }

    /**
     * @return ChunkReviewStruct[]
     */
    public function getChunkReviews() {
        return $this->chunk_reviews;
    }

    /**
     * @param ChunkReviewStruct $chunk_review
     */
    public function addChunkReview( ChunkReviewStruct $chunk_review ) {
        if ( false === isset( $this->chunk_reviews[ $chunk_review->id ] ) ) {
            $this->chunk_reviews[ $chunk_review->id ] = $chunk_review;
        }
    }

    /**
     * @return EntryWithCategoryStruct[]
     */
    public function getIssuesToDelete() {
        return $this->issues_to_delete;
    }

    /**
     * @param EntryWithCategoryStruct $issue
     */
    public function addIssueToDelete( EntryWithCategoryStruct $issue ) {
        if ( false === isset( $this->issues_to_delete[ $issue->id ] ) ) {
            $this->issues_to_delete[ $issue->id ] = $issue;
        }
    }
}