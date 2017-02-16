<?php

namespace LQA;

/**
 * Class ModelValidator
 * @package LQA
 *
 * @deprecated Struct validator are deprecated
 *
 */
class ModelValidator extends \DataAccess_AbstractValidator {

    static $VALID_TYPES = array(
        'points_per_thousand'
    );

    public function validate() {
        if ( !in_array( $this->struct->pass_type, self::$VALID_TYPES) ) {
            $this->errors[] = array('pass_type', $this->struct->pass_type . " is invalid ");
        }

        $encoded_options = json_decode( $this->struct->pass_options );
        if ( !$encoded_options ) {
            $this->errors[] = array('pass_options', "pass_options is not a valid json sting ");
        }
    }

}
