<?php

namespace Features;

use Chunks_ChunkStruct;
use Features\TranslationVersions\Handlers\DummyTranslationVersionHandler;
use Features\TranslationVersions\Handlers\TranslationVersionsHandler;
use Projects_ProjectStruct;
use Users_UserStruct;

class TranslationVersions extends BaseFeature {

    const FEATURE_CODE = 'translation_versions';

    /**
     * @param Chunks_ChunkStruct $chunkStruct
     * @param $id_segment
     * @param Users_UserStruct $userStruct
     * @param Projects_ProjectStruct $projectStruct
     * @return DummyTranslationVersionHandler|TranslationVersionsHandler
     */
    public static function getVersionHandlerNewInstance( Chunks_ChunkStruct $chunkStruct, $id_segment, Users_UserStruct $userStruct, Projects_ProjectStruct $projectStruct ) {

        if ( $projectStruct->isFeatureEnabled( self::FEATURE_CODE ) ) {
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
