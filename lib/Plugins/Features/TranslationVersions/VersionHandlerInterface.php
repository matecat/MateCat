<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 29/11/19
 * Time: 17:03
 *
 */

namespace Plugins\Features\TranslationVersions;


use Model\Propagation\PropagationResult;
use Model\Translations\SegmentTranslationStruct;

interface VersionHandlerInterface
{

    /**
     * Evaluates the need to save a new translation version to database.
     * If so, sets the new version number on $new_translation.
     *
     * @param SegmentTranslationStruct $new_translation
     * @param SegmentTranslationStruct $old_translation
     *
     * @return bool
     */
    public function saveVersionAndIncrement(SegmentTranslationStruct $new_translation, SegmentTranslationStruct $old_translation): bool;

    public function storeTranslationEvent(StoreTranslationEventParams $params): void;

     /**
      * @param SegmentTranslationStruct $translationStruct
      *
      * @return PropagationResult Always an object. An implementation that propagates nothing returns
      *                           `PropagationResult::empty()` rather than an array missing every key,
      *                           so callers never guard on presence.
      */
     public function propagateTranslation(SegmentTranslationStruct $translationStruct): PropagationResult;

}