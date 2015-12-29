<?php

namespace Features ;

class BaseFeature {

    protected $project_structure;
    protected $feature;


    /**
     * Warning: passing a $projectStructure prevents the possibility to pass
     * a persisted project in the future. TODO: this is likely to be reworked
     * in the future.
     *
     * The ideal solution would be to use a ProjectStruct for both persisted and
     * unpersisted scenarios, so to work with the same input structure every time.
     *
     */
    public function __construct( $projectStructure, $feature ) {
        $this->project_structure = $projectStructure ;
        $this->feature = $feature ;

    }

    public function postCreate() {

    }
}
