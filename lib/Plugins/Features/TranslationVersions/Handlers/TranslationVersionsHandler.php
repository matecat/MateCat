<?php

namespace Features\TranslationVersions\Handlers;

use Constants_TranslationStatus;
use Exception;
use Features\ReviewExtended\BatchReviewProcessor;
use Features\TranslationEvents\Model\TranslationEvent;
use Features\TranslationEvents\TranslationEventsHandler;
use Features\TranslationVersions\Model\TranslationVersionDao;
use Features\TranslationVersions\Model\TranslationVersionStruct;
use Features\TranslationVersions\VersionHandlerInterface;
use FeatureSet;
use Model\Jobs\JobDao;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectDao;
use Model\Projects\ProjectStruct;
use RuntimeException;
use Translations_SegmentTranslationDao;
use Translations_SegmentTranslationStruct;
use Users_UserStruct;
use Utils;

/**
 * Class TranslationVersionsHandler
 *
 */
class TranslationVersionsHandler implements VersionHandlerInterface {

    /**
     * @var TranslationVersionDao
     */
    private TranslationVersionDao $dao;

    /**
     * @var int
     */
    private int $id_job;

    /**
     * @var JobStruct
     */
    private JobStruct $chunkStruct;

    /**
     * @var int
     */
    private int $id_segment;

    /**
     * @var int
     */
    private int           $uid;
    private ProjectStruct $projectStruct;

    /**
     * TranslationVersionsHandler constructor.
     *
     * @param JobStruct        $chunkStruct
     * @param int|null         $id_segment
     * @param Users_UserStruct $userStruct
     * @param ProjectStruct    $projectStruct
     */
    public function __construct( JobStruct $chunkStruct, ?int $id_segment, Users_UserStruct $userStruct, ProjectStruct $projectStruct ) {

        $this->chunkStruct = $chunkStruct;
        $this->id_job      = $chunkStruct->id;
        $this->id_segment  = $id_segment;
        $this->uid         = $userStruct->uid;
        $this->dao         = new TranslationVersionDao();
        $this->projectStruct = $projectStruct;

    }

    /**
     * Save the current version and perform up-count
     *
     * If returns true it means that a new version of the parent segment was persisted
     *
     * @param Translations_SegmentTranslationStruct $new_translation
     * @param Translations_SegmentTranslationStruct $old_translation
     *
     * @return false|int
     */
    public function saveVersionAndIncrement( Translations_SegmentTranslationStruct $new_translation, Translations_SegmentTranslationStruct $old_translation ) {

        $version_saved = $this->saveVersion( $new_translation, $old_translation );

        if ( $version_saved ) {
            $new_translation->version_number = $old_translation->version_number + 1;
        } else {
            $new_translation->version_number = $old_translation->version_number ?? 0;
        }

        return $version_saved;
    }

    /**
     * @throws Exception
     */
    public function propagateTranslation( Translations_SegmentTranslationStruct $translationStruct ): array {
        return Translations_SegmentTranslationDao::propagateTranslation(
                $translationStruct,
                $this->chunkStruct,
                $this->id_segment,
                $this->projectStruct,
        );
    }

    /**
     * Evaluates the need to save a new translation version to the database.
     *
     * @param Translations_SegmentTranslationStruct $new_translation
     * @param Translations_SegmentTranslationStruct $old_translation
     *
     * @return bool|int
     */
    private function saveVersion(
            Translations_SegmentTranslationStruct $new_translation,
            Translations_SegmentTranslationStruct $old_translation
    ) {

        if ( Utils::stringsAreEqual( $new_translation->translation, $old_translation->translation ?? '' ) ) {
            return false;
        }

        // avoid version_number null error
        if ( $new_translation->version_number === null ) {
            $new_translation->version_number = 0;
        }

        // avoid version_number null error
        if ( $old_translation->version_number === null ) {
            $old_translation->version_number = 0;
        }

        // From now on, translations are treated as arrays and get attributes attached
        // just to be passed to version save. Create two arrays for the purpose.
        $new_version             = new TranslationVersionStruct( $old_translation->toArray() );
        $new_version->old_status = Constants_TranslationStatus::$DB_STATUSES_MAP[ $old_translation->status ];
        $new_version->new_status = Constants_TranslationStatus::$DB_STATUSES_MAP[ $new_translation->status ];

        /**
         * In some cases, version 0 may already be there among saved_versions, because
         * an issue for ReviewExtended has been saved on version 0.
         *
         * In any other case, we expect the version record NOT to be there when we reach this point.
         *
         * @param TranslationVersionStruct $version
         *
         * @return bool|int
         *
         */
        $version_record = $this->dao->getVersionNumberForTranslation(
                $this->id_job,
                $this->id_segment,
                $new_version->version_number
        );

        if ( $version_record ) {
            return $this->dao->updateVersion( $new_version );
        }

        return $this->dao->saveVersion( $new_version );
    }


    /**
     * @throws Exception
     */
    public function storeTranslationEvent( $params ) {

        // evaluate if the record is to be created, either the
        // status changed, or the translation changed
        $user = $params[ 'user' ];

        /** @var Translations_SegmentTranslationStruct $translation */
        $translation = $params[ 'translation' ];

        /** @var Translations_SegmentTranslationStruct $old_translation */
        $old_translation = $params[ 'old_translation' ];

        $source_page_code = $params[ 'source_page_code' ];

        /** @var JobStruct $chunk */
        $chunk = $params[ 'chunk' ];

        /** @var FeatureSet $features */
        $features = $params[ 'features' ];

        /** @var ProjectStruct $project */
        $project = $params[ 'project' ];

        $sourceEvent = new TranslationEvent(
                $old_translation,
                $translation,
                $user,
                $source_page_code
        );

        $translationEventsHandler = new TranslationEventsHandler( $chunk );
        $translationEventsHandler->setFeatureSet( $features );
        $translationEventsHandler->addEvent( $sourceEvent );
        $translationEventsHandler->setProject( $project );

        // If propagated segments exist, start cycle here
        // There is no logic here, the version_number is simply got from $segmentTranslationBeforeChange and saved as is in translation events
        if ( isset( $params[ 'propagation' ][ 'segments_for_propagation' ][ 'propagated' ] ) and !empty( $params[ 'propagation' ][ 'segments_for_propagation' ][ 'propagated' ] ) ) {

            $segments_for_propagation = $params[ 'propagation' ][ 'segments_for_propagation' ][ 'propagated' ];
            $segmentTranslations      = [];

            if ( !empty( $segments_for_propagation[ 'not_ice' ] ) ) {
                $segmentTranslations = array_merge( $segmentTranslations, $segments_for_propagation[ 'not_ice' ][ 'object' ] );
            }

            if ( !empty( $segments_for_propagation[ 'ice' ] ) ) {
                $segmentTranslations = array_merge( $segmentTranslations, $segments_for_propagation[ 'ice' ][ 'object' ] );
            }

            foreach ( $segmentTranslations as $segmentTranslationBeforeChange ) {

                /** @var Translations_SegmentTranslationStruct $propagatedSegmentAfterChange */
                $propagatedSegmentAfterChange                      = clone $segmentTranslationBeforeChange;
                $propagatedSegmentAfterChange->translation         = $translation->translation;
                $propagatedSegmentAfterChange->status              = $translation->status;
                $propagatedSegmentAfterChange->autopropagated_from = $translation->id_segment; // nullable
                $propagatedSegmentAfterChange->time_to_edit        = 0;

                $propagatedEvent = new TranslationEvent(
                        $segmentTranslationBeforeChange,
                        $propagatedSegmentAfterChange,
                        $user,
                        $source_page_code
                );

                $propagatedEvent->setPropagationSource( false );
                $translationEventsHandler->addEvent( $propagatedEvent );
            }
        }

        try {
            $translationEventsHandler->save( new BatchReviewProcessor() );
            ( new JobDao() )->destroyCacheByProjectId( $chunk->id_project );
            ProjectDao::destroyCacheById( $chunk->id_project );
        } catch ( Exception $e ) {
            throw new RuntimeException( $e->getMessage(), -2000 );
        }


    }

}
