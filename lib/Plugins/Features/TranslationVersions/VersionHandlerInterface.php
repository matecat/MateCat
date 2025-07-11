<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 29/11/19
 * Time: 17:03
 *
 */

namespace Plugins\Features\TranslationVersions;


use Model\Translations\SegmentTranslationStruct;

interface VersionHandlerInterface {

    /**
     * Evaluates the need to save a new translation version to database.
     * If so, sets the new version number on $new_translation.
     *
     * @param \Model\Translations\SegmentTranslationStruct $new_translation
     * @param \Model\Translations\SegmentTranslationStruct $old_translation
     *
     * @return mixed
     */
    public function saveVersionAndIncrement( SegmentTranslationStruct $new_translation, SegmentTranslationStruct $old_translation );

    public function storeTranslationEvent( $params );

    public function propagateTranslation( SegmentTranslationStruct $translationStruct ): array;

}