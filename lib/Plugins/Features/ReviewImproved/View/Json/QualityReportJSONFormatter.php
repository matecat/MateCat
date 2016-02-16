<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/26/16
 * Time: 5:24 PM
 */

namespace Features\ReviewImproved\View\Json;

use Features\ReviewImproved\Model\QualityReportModel;

class QualityReportJSONFormatter
{

    public function __construct( QualityReportModel $model  ) {
        $this->model = $model ;
    }

    public function render( ) {


    }


}