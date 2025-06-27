<?php

namespace Features;

use Features\TranslationVersions\Handlers\DummyTranslationVersionHandler;
use Features\TranslationVersions\Handlers\TranslationVersionsHandler;
use Features\TranslationVersions\VersionHandlerInterface;
use Model\Jobs\JobStruct;
use Model\Projects\ProjectStruct;
use Users_UserStruct;

class TranslationVersions extends BaseFeature {

    const FEATURE_CODE = 'translation_versions';

    /**
     * @param \Model\Jobs\JobStruct $chunkStruct
     * @param Users_UserStruct      $userStruct
     * @param ProjectStruct         $projectStruct
     * @param int|null              $id_segment
     *
     * @return VersionHandlerInterface
     */
    public static function getVersionHandlerNewInstance( JobStruct $chunkStruct, Users_UserStruct $userStruct, ProjectStruct $projectStruct, ?int $id_segment = null ) {

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
