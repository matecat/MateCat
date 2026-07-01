<?php

namespace Controller\Views;

use Controller\Abstracts\BaseKleinViewController;
use Klein\App;
use Klein\Request;
use Klein\Response;
use Model\DataAccess\IDatabase;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 09/05/2025
 * Time: 12.08
 *
 */
class CustomPageView extends BaseKleinViewController
{

    public function __construct(IDatabase $database)
    {
        $app = new App();
        $app->register('getDatabase', fn() => $database);
        parent::__construct(Request::createFromGlobals(), new Response(), null, $app);
    }

}
