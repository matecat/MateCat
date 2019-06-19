<?php

namespace Features ;

use Exceptions\ControllerReturnException;
use Exceptions\ValidationError;
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

        $propagated_ids   = $params['propagated_ids'];
        $source_page_code = $params['source_page_code'];

        $event = new SegmentTranslationEventModel($old_translation,
                $translation, $user, $source_page_code );

        $event->setPropagatedIds( $propagated_ids ) ;

        /**
         * Here we check if saving the event generates an exception.
         * This callback is in the scope of setTranslationController which is ajax controller, so we need
         * to modify the result array to allow the browser to be notified of the error message.
         */
        try {
            $event->save() ;
        } catch ( ValidationError $e ) {
            $params['controller_result']['errors'] [] = [
                    'code' => -2000,
                    'message' => $e->getMessage()
            ];
            throw new ControllerReturnException( $e->getMessage(), -2000 ) ;
        }
    }

    public function filter_get_segments_optional_fields(){
        $options[ 'optional_fields' ] = [ 'st.version_number' ];
        return $options;
    }

}
