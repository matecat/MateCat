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
     * @return float|string
     */
    public function getPeePerc() {
        if ( is_null( $this->pe_effort_perc ) ) {
            $this->pe_effort_perc = round( ( 1 - MyMemory::TMS_MATCH( $this->suggestion, $this->translation ) ) * 100 );
        }

        return $this->pe_effort_perc;
    }
}