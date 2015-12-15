<?php

namespace Features ;

class ReviewImproved extends BaseFeature {

    public function postCreate() {
        $options = json_decode($this->feature->options);

        if ( $options->id_qa_model != null ) {
            $dao = new \Projects_ProjectDao( \Database::obtain() );
            $dao->updateField( $this->project, 'id_qa_model', $options->id_qa_model);
        }
    }

}
