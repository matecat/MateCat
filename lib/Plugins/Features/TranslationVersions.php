<?php

namespace Features;

use Chunks_ChunkStruct;
use Exceptions\ControllerReturnException;
use Exceptions\ValidationError;
use Features\TranslationVersions\Model\TranslationEvent;
use Features\TranslationVersions\Handlers\DummyTranslationVersionHandler;
use Features\TranslationVersions\Handlers\TranslationEventsHandler;
use Features\TranslationVersions\Handlers\TranslationVersionsHandler;
use FeatureSet;
use Jobs_JobDao;
use Projects_ProjectDao;
use Projects_ProjectStruct;
use Translations_SegmentTranslationStruct;
use Users_UserStruct;

class TranslationVersions extends BaseFeature {

    const FEATURE_CODE = 'translation_versions';

    public static function getVersionHandlerNewInstance( Chunks_ChunkStruct $chunkStruct, $id_segment, Users_UserStruct $userStruct, Projects_ProjectStruct $projectStruct ) {

        if ( $projectStruct->isFeatureEnabled( self::FEATURE_CODE ) ) {
            return new TranslationVersionsHandler(
                    $chunkStruct,
                    $id_segment,
                    $userStruct,
                    $projectStruct
            );
        }

        return new DummyTranslationVersionHandler();

    }

    public function preSetTranslationCommitted( $params ) {
        // evaluate if the record is to be created, either the
        // status changed or the translation changed
        $user = $params[ 'user' ];

        /** @var Translations_SegmentTranslationStruct $translation */
        $translation = $params[ 'translation' ];

        /** @var Translations_SegmentTranslationStruct $old_translation */
        $old_translation = $params[ 'old_translation' ];

        $source_page_code = $params[ 'source_page_code' ];

        /** @var Chunks_ChunkStruct $chunk */
        $chunk = $params[ 'chunk' ];

        /** @var FeatureSet $features */
        $features = $params[ 'features' ];

        /** @var Projects_ProjectStruct $project */
        $project = $params[ 'project' ];

        $sourceEvent = new TranslationEvent(
                $old_translation,
                $translation,
                $user,
                $source_page_code
        );

        $batchEventHandler = new TranslationEventsHandler( $chunk );
        $batchEventHandler->setFeatureSet( $features );
        $batchEventHandler->addEvent( $sourceEvent );
        $batchEventHandler->setProject( $project );

        // If propagated segments exist, start cycle here
        // @TODO COMPLETE REFACTORY IS NEEDED HERE!!!!!
        // There is no logic here, the version_number is simply got from $segmentTranslationBeforeChange and saved as is in translation events
        if ( isset( $params[ 'propagation' ][ 'segments_for_propagation' ][ 'propagated' ] ) and false === empty( $params[ 'propagation' ][ 'segments_for_propagation' ][ 'propagated' ] ) ) {

            $segments_for_propagation = $params[ 'propagation' ][ 'segments_for_propagation' ][ 'propagated' ];
            $segmentTranslations      = [];

            if ( false === empty( $segments_for_propagation[ 'not_ice' ] ) ) {
                $segmentTranslations = array_merge( $segmentTranslations, $segments_for_propagation[ 'not_ice' ][ 'object' ] );
            }

            if ( false === empty( $segments_for_propagation[ 'ice' ] ) ) {
                $segmentTranslations = array_merge( $segmentTranslations, $segments_for_propagation[ 'ice' ][ 'object' ] );
            }

            foreach ( $segmentTranslations as $segmentTranslationBeforeChange ) {

                /** @var Translations_SegmentTranslationStruct $propagatedSegmentAfterChange */
                $propagatedSegmentAfterChange                      = clone $segmentTranslationBeforeChange;
                $propagatedSegmentAfterChange->translation         = $translation->translation;
                $propagatedSegmentAfterChange->status              = $translation->status;
                $propagatedSegmentAfterChange->autopropagated_from = $translation->id_segment;
                $propagatedSegmentAfterChange->time_to_edit        = 0;

                $propagatedEvent = new TranslationEvent(
                        $segmentTranslationBeforeChange,
                        $propagatedSegmentAfterChange,
                        $user,
                        $source_page_code
                );

                $propagatedEvent->setPropagationSource( false );
                $batchEventHandler->addEvent( $propagatedEvent );
            }
        }

        try {
            $batchEventHandler->save();
            // $event->setChunkReviewsList( $chunkReviews ) ;
            ( new Jobs_JobDao() )->destroyCacheByProjectId( $chunk->id_project );
            Projects_ProjectDao::destroyCacheById( $chunk->id_project );
        } catch ( ValidationError $e ) {
            $params[ 'controller_result' ][ 'errors' ] [] = [
                    'code'    => -2000,
                    'message' => $e->getMessage()
            ];
            throw new ControllerReturnException( $e->getMessage(), -2000 );
        }
    }

    public function filter_get_segments_optional_fields() {
        $options[ 'optional_fields' ] = [ 'st.version_number' ];

        return $options;
    }

}
