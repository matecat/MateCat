<?php

namespace Features ;

class BaseFeature {

    protected $project;
    protected $feature;

    public function __construct( $project, $feature ) {
        $this->project = $project ;
        $this->feature = $feature ;
    }

    public function postCreate() {

    }
}
