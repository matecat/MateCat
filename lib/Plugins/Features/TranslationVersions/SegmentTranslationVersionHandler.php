<?php

namespace Features\TranslationVersions;

use Constants;
use Constants_TranslationStatus;
use Database;
use Exception;
use Features;
use Log;
use Projects_ProjectDao;
use Translations_SegmentTranslationStruct;
use Translations_TranslationVersionDao;
use Translations_TranslationVersionStruct;
use Utils;

/**
 * Class SegmentTranslationVersionHandler
 *
 */
class SegmentTranslationVersionHandler {
    public $db;

    /**
     * @var Translations_TranslationVersionDao
     */
    private $dao;

    private $id_job ;
    private $id_segment ;
    private $project ;
    private $feature_enalbed ;


    public function __construct($id_job, $id_segment, $uid, $id_project) {
        $this->id_job     = $id_job ;
        $this->id_segment = $id_segment ;
        $this->uid        = $uid ;


        // TODO: refactor, why id_project should be null
        if ( null !== $id_project ) {
            $this->project = Projects_ProjectDao::findById( $id_project );

            $this->feature_enalbed = $this->project->isFeatureEnabled(
                    Features::TRANSLATION_VERSIONS
            );
        }

        Log::doJsonLog( 'feature_enabled ' . var_export( $this->feature_enalbed, true ) );
    }

    /**
     * @param $params
     *
     * @throws Exception
     */
    public function savePropagation( $params ) {
        $params = Utils::ensure_keys( $params, [
                'propagation', 'job_data'
        ] );

        if ( $this->feature_enalbed !== true ) {
            return;
        }

        $this->prepareDao();

        $this->dao->savePropagation(
                $params[ 'propagation' ],
                $this->id_segment,
                $params[ 'job_data' ]
        );
    }

    /**
     * Evaluates the need to save a new translation version to database.
     * If so, sets the new version number on $new_translation.
     *
     * @param $old_translation
     * @param $new_translation
     * @param $page
     */

    public function saveVersion(
            Translations_SegmentTranslationStruct $new_translation,
            Translations_SegmentTranslationStruct $old_translation
    ) {

        /**
         * This is where we decide if a new translation version is to be generated.
         * This should be moved in a review_extended specific model.
         * TODO: refactor.
         *
         */

        if (
                !$this->feature_enalbed ||
                empty( $old_translation ) ||
                $this->translationIsEqual( $new_translation, $old_translation )
        ) {
            return false;
        }

        // From now on, translations are treated as arrays and get attributes attached
        // just to be passed to version save. Create two arrays for the purpose.
        $new_version = new Translations_TranslationVersionStruct( $old_translation->toArray() );

        // XXX: this is to be reviewed
        $new_version->is_review  = ( $old_translation->status == Constants_TranslationStatus::STATUS_APPROVED ) ? 1 : 0;
        $new_version->old_status = Constants_TranslationStatus::$DB_STATUSES_MAP[ $old_translation->status ];
        $new_version->new_status = Constants_TranslationStatus::$DB_STATUSES_MAP[ $new_translation->status ];

        /**
         * In some cases, version 0 may already be there among saved_versions, because
         * an issue for ReviewExtended has been saved on version 0.
         *
         * In any other case we expect the version record NOT to be there when we reach this point.
         *
         * @param Translations_TranslationVersionStruct $version
         *
         * @return bool|int
         *
         */

        $this->prepareDao();

        $version_record = $this->dao->getVersionNumberForTranslation(
                $this->id_job, $this->id_segment, $new_version->version_number
        );

        if ( $version_record ) {
            return $this->dao->updateVersion( $new_version );
        }

        return $this->dao->saveVersion( $new_version );
    }

    /**
     * translationIsEqual
     *
     * This function needs to handle a special case. When old translation has been saved from a pre-translated XLIFF,
     * encoding is different than the one receiveed from the UI. Quotes are different for instance.
     *
     * So we compare the decoded version of the two strings. Should always work.
     *
     * TODO: this may give false negatives when string changes but decoded version doesn't
     *
     * @param $new_translation
     * @param $old_translation
     *
     * @return bool
     */
    private function translationIsEqual(
            Translations_SegmentTranslationStruct $new_translation,
            Translations_SegmentTranslationStruct $old_translation
    ) {
        $old = html_entity_decode( $old_translation->translation, ENT_XML1 | ENT_QUOTES );
        $new = html_entity_decode( $new_translation->translation, ENT_XML1 | ENT_QUOTES );

        return $new == $old;
    }

    private function prepareDao() {
        $this->db               = Database::obtain();
        $this->dao              = new Translations_TranslationVersionDao( $this->db );
        $this->dao->source_page = $this->source_page;
    }

}
