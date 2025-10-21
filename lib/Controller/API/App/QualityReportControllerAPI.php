<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 1/26/16
 * Time: 2:32 PM
 */

namespace Controller\API\App;

use Controller\API\V3\QualityReportControllerAPI as V3QualityReportController;
use Exception;

class QualityReportControllerAPI extends V3QualityReportController {

    /**
     * @throws Exception
     */
    public function segments_for_ui() {
        $this->segments( true );
    }

}