<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 2/11/16
 * Time: 6:20 PM
 */

namespace Features\ReviewImproved\Decorator;

use Features\ReviewImproved\Model\QualityReportModel;

class QualityReportDecorator {

    /**
     * @var QualityReportModel
     */
    public $model;

    public function __construct( QualityReportModel $model ) {
        $this->model = $model ;
    }

    public function decorate() {

    }
}