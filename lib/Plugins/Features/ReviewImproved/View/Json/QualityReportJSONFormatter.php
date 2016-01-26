<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/26/16
 * Time: 5:24 PM
 */

namespace Features\ReviewImproved\View\Json;

use LQA\ChunkReviewStruct;

class QualityReportJSONFormatter
{

    public function __construct(  ) {

    }

    public function renderItem( ChunkReviewStruct $record ) {
        $row = array(
            'is_pass' => $record->is_pass,
            'score' => $record->score,
            'reviewed_words_count' => $record->reviewed_words_count
        );
        return $row;
    }


}