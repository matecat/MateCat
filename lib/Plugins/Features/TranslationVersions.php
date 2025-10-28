<?php

namespace Plugins\Features;

use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Model\Users\UserStruct;
use Plugins\Features\TranslationVersions\Handlers\DummyTranslationVersionHandler;
use Plugins\Features\TranslationVersions\Handlers\TranslationVersionsHandler;
use Plugins\Features\TranslationVersions\VersionHandlerInterface;

class TranslationVersions extends BaseFeature {

    const string FEATURE_CODE = 'translation_versions';

    /**
     * @param JobStruct     $chunkStruct
     * @param UserStruct    $userStruct
     * @param ProjectStruct $projectStruct
     * @param int|null      $id_segment
     *
     * @return VersionHandlerInterface
     */
    public static function getVersionHandlerNewInstance( JobStruct $chunkStruct, UserStruct $userStruct, ProjectStruct $projectStruct, ?int $id_segment = null ) {

        if ( $id_segment && $projectStruct->isFeatureEnabled( self::FEATURE_CODE ) ) {
            return new TranslationVersionsHandler(
                    $chunkStruct,
                    $id_segment,
                    $userStruct,
                    $projectStruct
            );
        }

        return new DummyTranslationVersionHandler();

    }

}
