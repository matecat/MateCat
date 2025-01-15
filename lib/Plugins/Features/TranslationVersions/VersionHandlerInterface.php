<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 29/11/19
 * Time: 17:03
 *
 */

namespace Features\TranslationVersions;


use Translations_SegmentTranslationStruct;

interface VersionHandlerInterface {

    /**
     * Evaluates the need to save a new translation version to database.
     * If so, sets the new version number on $new_translation.
     *
     * @param Translations_SegmentTranslationStruct $new_translation
     * @param Translations_SegmentTranslationStruct $old_translation
     *
     * @return mixed
     */
    public function saveVersionAndIncrement( Translations_SegmentTranslationStruct $new_translation, Translations_SegmentTranslationStruct $old_translation );

    public function storeTranslationEvent( $params );

}