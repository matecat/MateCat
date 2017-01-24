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

        /**
         * This is where we decide if a new translation version is to be generated.
         * This should be moved in a review_improved specific model.
         * TODO: refactor.
         *
         */

        if (
            ! $this->feature_enalbed ||
            empty( $old_translation ) ||
            $this->translationIsEqual( $new_translation, $old_translation )
        ) {
            return false;
        }

        $this->prepareDao();

        $new_translation['version_number'] += 1 ;

        return $this->dao->saveVersion( $old_translation );
    }

    /**
     * translationIsEqual
     *
     * Here we need to handle a special case, the one in which the old translation
     * is the first version that was popuplated by a pre-translated XLIFF with chars that
     * can be HTML encoded. In such case the old_translation contains the HTML entity,
     * while we receive the entity decoded even if the segment was not changed by the translator.
     *
     * html_entity_decode($old_translation['translation'], ENT_XML1 | ENT_QUOTES) resolves this issue.
     *
     * @param $new_translation
     * @param $old_translation
     */
    private function translationIsEqual( $new_translation, $old_translation ) {
        return html_entity_decode($old_translation['translation'], ENT_XML1 | ENT_QUOTES)  == $new_translation['translation'] ;
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
