<?php

namespace Controller\Views;

use Controller\Abstracts\BaseKleinViewController;
use Exception;

class XliffToTargetController extends BaseKleinViewController
{

    /**
     * @throws Exception
     */
    public function renderView(): never
    {
        $this->setView("xliffToTarget.html");
        $this->render();
    }

}
