<?php

namespace Features\TranslationVersions;


class SegmentTranslationModel {

    /**
     * @var \SegmentTranslationChangeVector
     */
    private $parent_model;

    public function __construct( \SegmentTranslationChangeVector $parent_model ) {
        $this->parent_model = $parent_model ;
    }

    public function triggersNewVersion() {
        return $this->parent_model->translationTextChanged();
    }


}