<?php

class OwnerFeatures_OwnerFeatureValidator extends DataAccess_AbstractValidator {

    private $struct ;

    public function __construct( $struct ) {
        $this->struct = $struct;
    }

    public function isValid() {
        // all validation logic for this struct goes here
    }

    public function ensureValid() {
        if ( !$this->isValid() ) {
            throw new \Exceptions\ValidationError('invalid');
        }
    }

}
