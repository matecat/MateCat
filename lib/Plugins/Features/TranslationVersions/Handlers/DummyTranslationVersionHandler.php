<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 29/11/19
 * Time: 17:00
 *
 */

namespace Plugins\Features\TranslationVersions\Handlers;


use Model\FeaturesBase\FeatureSet;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Translations\SegmentTranslationStruct;
use Model\Users\UserStruct;
use Plugins\Features\TranslationVersions\VersionHandlerInterface;

class DummyTranslationVersionHandler implements VersionHandlerInterface
{

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
    public function saveVersionAndIncrement(SegmentTranslationStruct $new_translation, SegmentTranslationStruct $old_translation): bool
    {
        return false;
    }

    /**
     * @param array{
     *     translation: SegmentTranslationStruct,
     *     old_translation: SegmentTranslationStruct,
     *     propagation: array<string, mixed>,
     *     chunk: JobStruct,
     *     user: UserStruct,
     *     source_page_code: int,
     *     features: FeatureSet,
     *     project: ProjectStruct
     * } $params
     */
    public function storeTranslationEvent(array $params): void
    {
    }

    /**
     * @return array{
     *     totals?: array<string, mixed>,
     *     propagated_ids?: int[],
     *     segments_for_propagation?: array<int, mixed>
     * }
     */
    public function propagateTranslation(SegmentTranslationStruct $translationStruct): array
    {
        return [];
    }

}