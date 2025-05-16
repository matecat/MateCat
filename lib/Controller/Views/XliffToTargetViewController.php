<?php

namespace Views;

use AbstractControllers\BaseKleinViewController;
use Exception;

class XliffToTargetViewController extends BaseKleinViewController {

    /**
     * @throws Exception
     */
    public function renderView() {
        $this->setView( "xliffToTarget.html" );
        $this->render();
    }

}
