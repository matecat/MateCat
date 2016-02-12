<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 2/11/16
 * Time: 8:51 PM
 */

namespace Features\ReviewImproved\Model;


class QualityReportModel {

    /**
     * @var \Jobs_JobStruct
     */
    private $job ;
    /**
     * @param \Jobs_JobStruct $job
     */
    public function __construct(\Jobs_JobStruct $job) {
        $this->job = $job ;
    }


}