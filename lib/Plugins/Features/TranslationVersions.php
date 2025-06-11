<?php

namespace Features;

use Features\TranslationVersions\Handlers\DummyTranslationVersionHandler;
use Features\TranslationVersions\Handlers\TranslationVersionsHandler;
use Features\TranslationVersions\VersionHandlerInterface;
use Jobs_JobStruct;
use Projects_ProjectStruct;
use Users_UserStruct;

class TranslationVersions extends BaseFeature {

    const FEATURE_CODE = 'translation_versions';

    /**
     * @param Jobs_JobStruct         $chunkStruct
     * @param Users_UserStruct       $userStruct
     * @param Projects_ProjectStruct $projectStruct
     * @param int|null               $id_segment
     *
     * @return VersionHandlerInterface
     */
    public static function getVersionHandlerNewInstance( Jobs_JobStruct $chunkStruct, Users_UserStruct $userStruct, Projects_ProjectStruct $projectStruct, ?int $id_segment = null ) {

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
