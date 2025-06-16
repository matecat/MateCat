<?php

namespace Views;

use AbstractControllers\BaseKleinViewController;
use Exception;

class XliffToTargetController extends BaseKleinViewController {

    /**
     * @throws Exception
     */
    public function renderView() {
        $this->setView( "xliffToTarget.html" );
        $this->render();
    }

}
