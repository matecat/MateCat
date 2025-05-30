<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 29/11/19
 * Time: 17:00
 *
 */

namespace Features\TranslationVersions\Handlers;


use Features\TranslationVersions\VersionHandlerInterface;
use Translations_SegmentTranslationStruct;

class DummyTranslationVersionHandler implements VersionHandlerInterface {

    /**
     * Evaluates the need to save a new translation version to database.
     * If so, set the new version number on $new_translation.
     *
     * Never set a new Version
     *
     * @param Translations_SegmentTranslationStruct $new_translation
     * @param Translations_SegmentTranslationStruct $old_translation
     *
     * @return bool
     */
    public function saveVersionAndIncrement( Translations_SegmentTranslationStruct $new_translation, Translations_SegmentTranslationStruct $old_translation ): bool {
        return false;
    }

    public function storeTranslationEvent( $params ) {
    }

    public function propagateTranslation( Translations_SegmentTranslationStruct $translationStruct ): array {
        return [];
    }

}