<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 24/11/2016
 * Time: 18:13
 */

namespace API\Commons;

use Bootstrap;
use Exception;

abstract class AbstractStatefulKleinController extends KleinController {

    protected bool $useSession = true;

    /**
     * AbstractStatefulKleinController constructor.
     *
     * @param $request
     * @param $response
     * @param $service
     * @param $app
     *
     * @throws Exception
     */
    public function __construct( $request, $response, $service, $app ) {
        parent::__construct( $request, $response, $service, $app );
    }

}