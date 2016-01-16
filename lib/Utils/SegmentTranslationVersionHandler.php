<?php

class SegmentTranslationVersionHandler {
    public $db;

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

    public function savePropagation( $params ) {
        $params = Utils::ensure_keys($params, array(
            'propagation', 'job_data', 'propagate_to_translated'
        ));

        if ( $this->feature_enalbed !== true ) {
            return ;
        }

        $this->prepareDao();

        $this->dao->savePropagation(
            $params['propagation'],
            $this->id_segment,
            $params['job_data'],
            $params['propagate_to_translated']
        );
    }

    public function saveVersion( $params ) {
        $params = (object) Utils::ensure_keys($params, array(
            'old_translation', 'new_translation'
        ));

        if ( $this->feature_enalbed !== true ) {
            return ;
        }

        if ( empty($params->old_translation ) ) {
            return;
        }

        if ( $params->old_translation['translation'] ==
            $params->new_translation['translation'] ) {
            return ;
        }

        $this->prepareDao();
        $this->dao->saveVersion( $params->old_translation );
    }

    private function prepareDao() {
        $this->db         = Database::obtain();
        $dao = new Translations_TranslationVersionDao( $this->db );
        $dao->uid         = $this->uid ;
        $dao->source_page = $this->source_page ;
        // TODO: ^^^ this is safe for now because we have
        // one connection for request, so the object returned
        // by the obtain is the same we started the transaction
        // on in setTranslation.

        $this->dao = $dao ;
    }

}
