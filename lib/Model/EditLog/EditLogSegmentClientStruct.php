<?php

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 05/10/15
 * Time: 12.59
 */
class EditLog_EditLogSegmentClientStruct extends EditLog_EditLogSegmentStruct {

    /**
     * @var string
     */
    public $display_time_to_edit;

    /**
     * @var float
     */
    public $secs_per_word;

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
    public $ter;

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
     * @return float|string
     */
    public function getPeePerc() {
        if ( is_null( $this->pe_effort_perc ) ) {
            $this->pe_effort_perc = $this->getPEE();
        }

        if ( $this->pe_effort_perc < 0 ) {
            $this->pe_effort_perc = 0;
        } else if ( $this->pe_effort_perc > 100 ) {
            $this->pe_effort_perc = 100;
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

        $this->warnings = implode( ", ", $this->warnings );
    }
}