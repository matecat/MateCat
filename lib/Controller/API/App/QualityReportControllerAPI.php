<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/26/16
 * Time: 2:32 PM
 */

namespace Controller\API\App;

use Controller\API\V3\QualityReportControllerAPI as V3QualityReportController;
use DivisionByZeroError;
use Exception;
use TypeError;

class QualityReportControllerAPI extends V3QualityReportController
{

    /**
     * @throws Exception
     * @throws DivisionByZeroError
     * @throws TypeError
     */
    public function segments_for_ui(): void
    {
        $this->segments(true);
    }

}