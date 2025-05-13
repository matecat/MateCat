<?php

namespace API\Commons;

use AbstractControllers\IController;
use Exception;
use INIT;
use PHPTAL;
use PHPTALWithAppend;
use Utils;

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 06/10/16
 * Time: 10:24
 */
class BaseKleinViewController extends AbstractStatefulKleinController implements IController {

    /**
     * @var PHPTALWithAppend
     */
    protected PHPTAL $view;

    /**
     * @var integer
     */
    protected int $httpCode;

    /**
     * @param $request
     * @param $response
     * @param $service
     * @param $app
     *
     * @throws Exception
     */
    public function __construct( $request, $response, $service, $app ) {
        parent::__construct( $request, $response, $service, $app );
        $this->timingLogFileName = 'view_controller_calls_time.log';
    }

    /**
     * @param       $template_name
     * @param array $params
     * @param int   $code
     *
     * @return void
     */
    public function setView( $template_name, array $params = [], int $code = 200 ) {

        try {

            $this->view     = new PHPTALWithAppend( INIT::$TEMPLATE_ROOT . "/$template_name" );
            $this->httpCode = $code;

            $this->view->{'basepath'}     = INIT::$BASEURL;
            $this->view->{'hostpath'}     = INIT::$HTTPHOST;
            $this->view->{'build_number'} = INIT::$BUILD_NUMBER;

            /**
             * This is a unique ID generated at runtime.
             * It is injected into the nonce attribute of `< script >` tags to allow browsers to safely execute the contained CSS and JavaScript.
             */
            $this->view->{'x_nonce_unique_id'}          = Utils::uuid4();
            $this->view->{'x_self_ajax_location_hosts'} = INIT::$ENABLE_MULTI_DOMAIN_API ? " *.ajax." . parse_url( INIT::$HTTPHOST )[ 'host' ] : null;

            foreach ( $params as $key => $value ) {
                if ( is_array( $value ) ) {
                    $this->view->{$key} = json_encode( $value );
                } else {
                    $this->view->{$key} = $value;
                }
            }

            $this->view->setOutputMode( PHPTAL::HTML5 );

        } catch ( Exception $ignore ) {

        }

    }

    /**
     * @param $httpCode integer
     */
    public function setCode( int $httpCode ) {
        $this->httpCode = $httpCode;
    }

    /**
     * @param int|null $code
     *
     * @return void
     */
    public function render( ?int $code = null ) {
        $this->response->code( $code ?? $this->httpCode );
        $this->response->body( $this->view->execute() );
        $this->response->send();
        die();
    }

}