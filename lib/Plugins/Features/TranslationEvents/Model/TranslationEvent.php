<?php

namespace Features\TranslationEvents\Model;

use Chunks_ChunkStruct;
use Constants;
use Constants_TranslationStatus;
use Database;
use Exception;
use LQA\ChunkReviewStruct;
use LQA\EntryWithCategoryStruct;
use Segments_SegmentDao;
use Segments_SegmentStruct;
use Translations_SegmentTranslationStruct;
use Users_UserDao;
use Users_UserStruct;

class TranslationEvent {

    /**
     * @var Translations_SegmentTranslationStruct
     */
    protected Translations_SegmentTranslationStruct $old_translation;

    /**
     * @var Translations_SegmentTranslationStruct
     */
    protected Translations_SegmentTranslationStruct $wanted_translation;

    /**
     * @var Users_UserStruct|null
     */
    protected ?Users_UserStruct $user;

    /**
     * @var int
     */
    protected int $source_page;

    /**
     * @var TranslationEventStruct|null
     */
    protected ?TranslationEventStruct $previous_event;

    /**
     * @var TranslationEventStruct
     */
    protected TranslationEventStruct $translation_event_struct;

    protected bool $_isPropagationSource = true;

    /**
     * @var Chunks_ChunkStruct
     */
    private Chunks_ChunkStruct $chunk;

    /**
     * @var bool
     */
    private bool $prepared = false;

    /**
     * @var bool
     */
    private bool $revisionFlagAllowed = true;

    /**
     * @var array
     */
    private array $unsetFinalRevision = [];

    /**
     * @var ChunkReviewStruct[]
     */
    private array $chunk_reviews_partials_to_update = [];

    /**
     * @var EntryWithCategoryStruct[]
     */
    private array $issues_to_delete = [];

    public function __construct( Translations_SegmentTranslationStruct $old_translation,
                                 Translations_SegmentTranslationStruct $translation,
                                 ?Users_UserStruct                     $user,
                                 int                                   $source_page_code
    ) {

        $this->old_translation    = $old_translation;
        $this->wanted_translation = $translation;
        $this->user               = $user;
        $this->source_page        = $source_page_code;
        $this->chunk              = $this->wanted_translation->getChunk();

        $this->getLatestEventForSegment();
    }


    /**
     * @return Translations_SegmentTranslationStruct
     */
    public function getWantedTranslation(): Translations_SegmentTranslationStruct {
        return $this->wanted_translation;
    }

    /**
     * @return Users_UserStruct|null
     * @throws Exception
     */
    public function getUser(): ?Users_UserStruct {

        if ( isset( $this->user ) && $this->user->uid ) {
            return $this->user;
        }

        return null;
    }

    public function getSourcePage(): int {
        return $this->source_page;
    }

    /**
     * @return Translations_SegmentTranslationStruct
     * @throws Exception
     */
    public function getOldTranslation(): Translations_SegmentTranslationStruct {
        return $this->old_translation;
    }

    public function isADraftChange(): bool {
        return $this->statusAsSourcePage( $this->wanted_translation->status ) == 0;
    }

    public function isChangingStatus(): bool {
        return $this->old_translation->status !== $this->wanted_translation->status;
    }

    public function isIce(): bool {
        return $this->old_translation->isICE();
    }

    public function isUnModifiedIce(): bool {
        return $this->isIce() &&
                $this->old_translation->version_number == 0 &&
                $this->wanted_translation->version_number == 0;
    }

    /**
     * @return Segments_SegmentStruct
     */
    public function getSegmentStruct(): ?Segments_SegmentStruct {
        $dao = new Segments_SegmentDao( Database::obtain() );

        return $dao->getByChunkIdAndSegmentId(
                $this->chunk->id,
                $this->chunk->password,
                $this->wanted_translation->id_segment
        );
    }

    /**
     * @return Chunks_ChunkStruct
     */
    public function getChunk(): ?Chunks_ChunkStruct {
        return $this->chunk;
    }

    /**
     * @param ChunkReviewStruct $chunkReview
     *
     * @return bool
     * @throws Exception
     */
    public function currentEventIsOnThisChunk( ChunkReviewStruct $chunkReview ): bool {
        return $this->getCurrentEventSourcePage() == $chunkReview->source_page;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function isLowerTransition(): bool {
        return $this->statusAsSourcePage( $this->old_translation->status ) > $this->statusAsSourcePage( $this->wanted_translation->status );
    }

    /**
     * @return bool
     */
    public function isPrepared(): bool {
        return $this->prepared;
    }

    /**
     * @param bool $prepared
     *
     * @return $this
     */
    public function setPrepared( bool $prepared ): TranslationEvent {
        $this->prepared = $prepared;

        return $this;
    }

    /**
     * This may return null in some cases because prior event can be missing.
     *
     * @return TranslationEventStruct|null
     */
    private function getLatestEventForSegment(): ?TranslationEventStruct {
        if ( empty( $this->previous_event ) ) {
            $this->previous_event = ( new TranslationEventDao() )->getLatestEventForSegment(
                    $this->old_translation->id_job,
                    $this->old_translation->id_segment
            );
        }

        return $this->previous_event;
    }

    /**
     * @return TranslationEventStruct
     * @throws Exception
     */
    public function getTranslationEventStruct(): TranslationEventStruct {
        if ( !isset( $this->translation_event_struct ) ) {
            throw new Exception( 'The current segment was not prepared yet. Run TranslationEventsHandler::prepareEventStruct() first.' );
        }

        return $this->translation_event_struct;
    }

    /**
     * @param TranslationEventStruct $translation_event_struct
     *
     * @return $this
     */
    public function setTranslationEventStruct( TranslationEventStruct $translation_event_struct ): TranslationEvent {
        $this->translation_event_struct = $translation_event_struct;

        return $this;
    }

    /**
     * @return int
     * @throws Exception
     */
    public function getPreviousEventSourcePage(): int {
        if ( !$this->getLatestEventForSegment() ) {
            if (
                    in_array( $this->getOldTranslation()->status,
                            array_merge(
                                    Constants_TranslationStatus::$TRANSLATION_STATUSES,
                                    Constants_TranslationStatus::$INITIAL_STATUSES
                            ) )
            ) {
                $source_page = Constants::SOURCE_PAGE_TRANSLATE;
            } elseif ( $this->getOldTranslation()->status == Constants_TranslationStatus::STATUS_APPROVED ) {
                $source_page = Constants::SOURCE_PAGE_REVISION;
            } elseif ( $this->getOldTranslation()->status == Constants_TranslationStatus::STATUS_APPROVED2 ) {
                $source_page = Constants::SOURCE_PAGE_REVISION_2;
            } else {
                throw new Exception( 'Unable to guess source_page for missing prior event' );
            }

            return $source_page;
        } else {
            return $this->getLatestEventForSegment()->source_page;
        }
    }

    /**
     * @param $status
     *
     * @return int
     */
    private function statusAsSourcePage( $status ): int {

        switch ( $status ) {
            case $status == Constants_TranslationStatus::STATUS_TRANSLATED:
                return Constants::SOURCE_PAGE_TRANSLATE;
            case $status == Constants_TranslationStatus::STATUS_APPROVED:
                return Constants::SOURCE_PAGE_REVISION;
            case $status == Constants_TranslationStatus::STATUS_APPROVED2:
                return Constants::SOURCE_PAGE_REVISION_2;
            default:
                return 0;
        }

    }

    /**
     * @return int
     * @throws Exception
     */
    public function getCurrentEventSourcePage(): int {
        return $this->getTranslationEventStruct()->source_page;
    }

    /**
     * @return bool
     */
    public function isPropagationSource(): bool {
        return $this->_isPropagationSource;
    }

    /**
     * @param bool $value
     *
     * @return void
     */
    public function setPropagationSource( bool $value ): void {
        $this->_isPropagationSource = $value;
    }

    /**
     * This flag is meant to force setting the final_revision flag to 0
     * For events like a "GREEN" ICE acceptance without modification in R1 phase.
     * These events by definition should be registered but not set as final_revision (no modification means any revision)
     *
     * @return bool
     */
    public function isFinalRevisionFlagAllowed(): bool {
        return $this->revisionFlagAllowed;
    }

    /**
     * @param bool $revisionFlagAllowed
     *
     * @return $this
     */
    public function setRevisionFlagAllowed( bool $revisionFlagAllowed ): TranslationEvent {
        $this->revisionFlagAllowed = $revisionFlagAllowed;

        return $this;
    }

    /**
     * @return int[]
     */
    public function getUnsetFinalRevision(): array {
        return $this->unsetFinalRevision;
    }

    /**
     * @param int $source_page
     */
    public function setFinalRevisionToRemove( int $source_page ) {
        $this->unsetFinalRevision[] = $source_page;
    }

    /**
     * @return ChunkReviewStruct[]
     */
    public function getChunkReviewsPartials(): array {
        return $this->chunk_reviews_partials_to_update;
    }

    /**
     * @param ChunkReviewStruct $chunk_review
     */
    public function setChunkReviewForPassFailUpdate( ChunkReviewStruct $chunk_review ) {
        if ( false === isset( $this->chunk_reviews_partials_to_update[ $chunk_review->id ] ) ) {
            $this->chunk_reviews_partials_to_update[ $chunk_review->id ] = $chunk_review;
        }
    }

    /**
     * @return EntryWithCategoryStruct[]
     */
    public function getIssuesToDelete(): array {
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