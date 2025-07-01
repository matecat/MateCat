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
use Model\Translations\SegmentTranslationStruct;

class DummyTranslationVersionHandler implements VersionHandlerInterface {

    /**
     * Evaluates the need to save a new translation version to database.
     * If so, set the new version number on $new_translation.
     *
     * Never set a new Version
     *
     * @param SegmentTranslationStruct $new_translation
     * @param SegmentTranslationStruct $old_translation
     *
     * @return bool
     */
    public function saveVersionAndIncrement( SegmentTranslationStruct $new_translation, SegmentTranslationStruct $old_translation ): bool {
        return false;
    }

    public function storeTranslationEvent( $params ) {
    }

    public function propagateTranslation( SegmentTranslationStruct $translationStruct ): array {
        return [];
    }

}