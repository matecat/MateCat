<?php

/**
 *
 * This is the model used in controllers to save LQA Entry records.
 * It's constructor takes the same data the EntryStruct requires.
 */
namespace LQA;

class TranslationIssueModel {

    public $struct ;
    private $struct_data ;

    public function __construct( $struct_data ) {
        $this->struct_data  = $struct_data;
        $this->struct = new EntryStruct( $struct_data );
    }

    /**
     * Saves the model to database, invoking callbacks
     * as needed.
     */
    public function save() {
        $saved_struct = EntryDao::createEntry( $this->struct_data );
        return $saved_struct ;
    }



}
