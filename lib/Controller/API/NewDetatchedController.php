<?php

class NewDetatchedController extends NewController {

    public function __construct() {
        parent::__construct();
    }

    protected function _pollForCreationResult() {
        $this->result['errors'] = $this->projectStructure[ 'result' ][ 'errors' ]->getArrayCopy();
    }
}