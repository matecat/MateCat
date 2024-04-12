<?php
namespace EditLog;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 05/10/15
 * Time: 12.59
 */
class EditLogSegmentClientStruct extends EditLogSegmentStruct {

    /**
     * @var string
     */
    public $display_time_to_edit;

    /**
     * @var string
     */
    public $stats_valid;

    /**
     * @var string
     */
    public $stats_valid_color;

    /**
     * @var string
     */
    public $stats_valid_style;

    /**
     * @var float
     */
    public $pe_effort_perc;

    /**
     * @var string
     */
    public $diff;

    /**
     * @var string
     */
    public $suggestion_view;

    /**
     * @var string
     */
    public $source_csv;

    /**
     * @var string
     */
    public $translation_csv;

    /**
     * @var string
     */
    public $sug_csv;

    /**
     * @var string
     */
    public $diff_csv;

    /**
     * @var int
     */
    public $num_translation_mismatch;

    /**
     * @var bool
     */
    public $ice_modified = false;

    /**
     * @var bool
     */
    public $locked = false;

    /**
     * @return float|string
     */
    public function getPEE() {

        if ( is_null( $this->pe_effort_perc ) ) {
            $this->pe_effort_perc = parent::getPEE();
        }

        return $this->pe_effort_perc;
    }

    public function evaluateWarningString() {
        $this->getWarning();

        $num_translation_mismatches = $this->num_translation_mismatch;

        if ( !empty( $num_translation_mismatches ) ) {
            $this->warnings[] = sprintf(
                    "Translation Mismatch ( %d )",
                    $num_translation_mismatches
            );
        }

        if( $this->isICEModified() ){
            $this->ice_modified = true;
        }

        $this->warnings = implode( ", ", $this->warnings );
    }

    public function isICEModified(){
        return ( $this->getPEE() != 0 && $this->isICE() );
    }

    public function isICE(){
        return ( $this->match_type == 'ICE' && $this->locked );
    }

}