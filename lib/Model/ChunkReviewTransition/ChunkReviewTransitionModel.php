<?php

class ChunkReviewTransitionDao_ChunkReviewTransitionModel {

    /**
     * @var \LQA\ChunkReviewStruct[]
     */
    private $chunk_reviews;

    /**
     * @var \LQA\EntryWithCategoryStruct[]
     */
    private $issues_to_delete;

    /**
     * @return \LQA\ChunkReviewStruct[]
     */
    public function getChunkReviews() {
        return $this->chunk_reviews;
    }

    /**
     * @param \LQA\ChunkReviewStruct $chunk_review
     */
    public function addChunkReview( \LQA\ChunkReviewStruct $chunk_review ) {
        if ( false === array_key_exists($chunk_review->id, $this->chunk_reviews) ) {
            $this->chunk_reviews[$chunk_review->id] = $chunk_review;
        }
    }

    /**
     * @return \LQA\EntryWithCategoryStruct[]
     */
    public function getIssuesToDelete() {
        return $this->issues_to_delete;
    }

    /**
     * @param \LQA\EntryWithCategoryStruct $issue
     */
    public function addIssueToDelete( \LQA\EntryWithCategoryStruct $issue ) {
        if ( false === array_key_exists($issue->id, $this->issues_to_delete) ) {
            $this->issues_to_delete[$issue->id] = $issue;
        }
    }
}