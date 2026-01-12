<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 29/11/19
 * Time: 17:03
 *
 */

namespace Plugins\Features\TranslationVersions;


use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Translations\SegmentTranslationStruct;
use Model\Users\UserStruct;

interface VersionHandlerInterface
{

    /**
     * Evaluates the need to save a new translation version to database.
     * If so, sets the new version number on $new_translation.
     *
     * @param SegmentTranslationStruct $new_translation
     * @param SegmentTranslationStruct $old_translation
     *
     * @return mixed
     */
    public function saveVersionAndIncrement(SegmentTranslationStruct $new_translation, SegmentTranslationStruct $old_translation): bool;

    /**
     * @param $params array{
     *     translation: SegmentTranslationStruct,
     *     old_translation: SegmentTranslationStruct,
     *     propagation: array{
     *          totals: array,
     *          propagated_ids: int[],
     *          segments_for_propagation: array
     *     },
     *     chunk: JobStruct,
     *     segment: string,
     *     user: UserStruct,
     *     source_page_code: int,
     *     features: FeatureSet,
     *     project: ProjectStruct
     * }
     *
     *
     * @return void
     */
    public function storeTranslationEvent(array $params): void;

    /**
     * @param SegmentTranslationStruct $translationStruct
     *
     * @return array
     */
    public function propagateTranslation(SegmentTranslationStruct $translationStruct): array;

}