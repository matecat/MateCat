<?php

class OwnerFeatures_OwnerFeatureValidator extends DataAccess_AbstractValidator {

    public function isValid() {
        // all validation logic for this struct goes here
    }

    public function ensureValid() {
        if ( !$this->isValid() ) {
            throw new \Exceptions\ValidationError('invalid');
        }
    }

    public function validate() {

    }

}
