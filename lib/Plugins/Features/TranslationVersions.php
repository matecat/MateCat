<?php

namespace Features ;

use Exceptions\ControllerReturnException;
use Exceptions\ValidationError;
use Features\ReviewExtended\Model\ChunkReviewDao;
use Features\TranslationVersions\Model\SegmentTranslationEventModel;

class TranslationVersions extends BaseFeature {

    const FEATURE_CODE = 'translation_versions';

    public function preSetTranslationCommitted( $params ) {
        // evaluate if the record is to be created, either the
        // status changed or the translation changed
        $user = $params['user'] ;
        /** @var \Translations_SegmentTranslationStruct $translation */
        $translation = $params['translation'] ;
        /** @var \Translations_SegmentTranslationStruct $old_translation */
        $old_translation  = $params['old_translation'];
        $source_page_code = $params['source_page_code'];
        $chunk            = $params['chunk'];

        $chunkReviews = ( new ChunkReviewDao() )->findAllChunkReviewsByChunkIds( [ [ $chunk->id, $chunk->password ] ] );

        $events = [] ;
        $sourceEvent = new SegmentTranslationEventModel($old_translation, $translation, $user, $source_page_code );

        /** @var SegmentTranslationEventModel[] $events */
        $events[] = $sourceEvent ;

        // Start cycle for propagated segments
        foreach( $params['propagation']['propagated_segments'] as $segmentTranslationBeforeChange ) {
            /** @var \Translations_SegmentTranslationStruct $propagatedSegmentAfterChange */
            $propagatedSegmentAfterChange                      = clone $segmentTranslationBeforeChange ;
            $propagatedSegmentAfterChange->translation         = $translation->translation ;
            $propagatedSegmentAfterChange->status              = $translation->status ;
            $propagatedSegmentAfterChange->autopropagated_from = $translation->id_segment ;
            $propagatedSegmentAfterChange->time_to_edit        = 0 ;

            $propagatedEvent = new SegmentTranslationEventModel(
                    $segmentTranslationBeforeChange,
                    $propagatedSegmentAfterChange,
                    $user,
                    $source_page_code
            ) ;

            $propagatedEvent->setPropagationSource( false ) ;
            $events[] = $propagatedEvent ;
        }

        foreach( $events as $event ) {
            try {
                $event->setChunkReviewsList( $chunkReviews ) ;
                $event->save() ;
            } catch ( ValidationError $e ) {
                $params['controller_result']['errors'] [] = [
                        'code' => -2000,
                        'message' => $e->getMessage()
                ];
                throw new ControllerReturnException( $e->getMessage(), -2000 ) ;
            }
        }
    }

    public function filter_get_segments_optional_fields(){
        $options[ 'optional_fields' ] = [ 'st.version_number' ];
        return $options;
    }

}
