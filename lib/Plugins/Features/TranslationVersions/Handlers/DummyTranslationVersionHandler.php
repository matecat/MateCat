<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 29/11/19
 * Time: 17:00
 *
 */

namespace Plugins\Features\TranslationVersions\Handlers;


use Model\Translations\SegmentTranslationStruct;
use Plugins\Features\TranslationVersions\VersionHandlerInterface;

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