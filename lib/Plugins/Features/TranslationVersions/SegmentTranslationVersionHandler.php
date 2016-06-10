<?php

namespace Features\TranslationVersions ;

use Translations_TranslationVersionDao;
use Constants;
use Projects_ProjectDao;
use Features ;
use Log, \Exception, Utils, \Database;

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
    private $source_page ;

    public function __construct($id_job, $id_segment, $uid, $id_project, $is_review) {
        $this->id_job     = $id_job ;
        $this->id_segment = $id_segment ;
        $this->uid        = $uid ;
        $this->source_page  = ( $is_review ?
            Constants::SOURCE_PAGE_REVISION :
            Constants::SOURCE_PAGE_TRANSLATE
        );

        if (null !== $id_project) {
            $this->project = Projects_ProjectDao::findById( $id_project );

            $this->feature_enalbed = $this->project->isFeatureEnabled(
                Features::TRANSLATION_VERSIONS
            );
        }

        Log::doLog( 'feature_enalbed', var_export( $this->feature_enalbed, true));
    }

    /**
     * @param $params
     * @throws Exception
     */
    public function savePropagation( $params ) {
        $params = Utils::ensure_keys($params, array(
            'propagation', 'job_data'
        ));

        if ( $this->feature_enalbed !== true ) {
            return ;
        }

        $this->prepareDao();

        $this->dao->savePropagation(
            $params['propagation'],
            $this->id_segment,
            $params['job_data']
        );
    }

    /**
     * Evaluates the need to save a new translation version to database.
     * If so, sets the new version number on $new_translation.
     *
     * @param $old_translation
     * @param $new_translation
     */

    public function saveVersion( $new_translation, $old_translation ) {

        if ( $this->feature_enalbed !== true ) {
            return false;
        }

        if ( empty($old_translation ) ) {
            return false;
        }

        /**
         * This is where we decide if a new translation version is to be generated.
         * This should be moved in a review_improved specific model.
         * TODO: refactor.
         */
        if ( $old_translation['translation'] ==
            $new_translation['translation'] ) {
            return false;
        }

        $this->prepareDao();

        $new_translation['version_number'] += 1 ;

        return $this->dao->saveVersion( $old_translation );
    }

    private function prepareDao() {
        $this->db         = Database::obtain();
        $this->dao = new Translations_TranslationVersionDao( $this->db );
        $this->dao->uid         = $this->uid ;
        $this->dao->source_page = $this->source_page ;

        // TODO: ^^^ this is safe for now because we have
        // one connection for request, so the object returned
        // by the obtain is the same we started the transaction
        // on in setTranslation.

    }

}
