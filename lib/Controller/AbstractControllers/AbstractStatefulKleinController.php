<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 24/11/2016
 * Time: 18:13
 */

namespace AbstractControllers;

use Exception;
use Klein\App;
use Klein\Request;
use Klein\Response;
use Klein\ServiceProvider;

abstract class AbstractStatefulKleinController extends KleinController implements IController {

    protected bool $useSession = true;

    /**
     * @param Request              $request
     * @param Response             $response
     * @param ServiceProvider|null $service
     * @param App|null             $app
     *
     * @throws Exception
     */
    public function __construct( Request $request, Response $response, ?ServiceProvider $service = null, ?App $app = null ) {
        parent::__construct( $request, $response, $service, $app );
    }

}