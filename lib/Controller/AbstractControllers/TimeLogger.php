<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/05/19
 * Time: 19.12
 *
 */

namespace AbstractControllers;


use Log;
use Utils;

trait TimeLogger {

    protected $timingLogFileName  = 'ui_calls_time.log';
    protected $timingCustomObject = [];

    protected $startExecutionTime;

    protected function startTimer() {
        $this->startExecutionTime = microtime( true );
    }

    public function getTimer() {
        return round( microtime( true ) - $this->startExecutionTime, 4 ); //get milliseconds
    }

    protected function logPageCall() {

        if ( !$this->userIsLogged() && $this instanceof \controller ) {
            $this->readLoginInfo( true );
        }

        Log::$fileName = $this->timingLogFileName;

        /** @var $this IController|TimeLogger */

        $_request_uri = parse_url( $_SERVER[ 'REQUEST_URI' ] );
        if( isset( $_request_uri[ 'query' ] ) ){
            parse_str( $_request_uri[ 'query' ], $str );
            $_request_uri[ 'query' ] = $str;
        }

        $object = [
                "client_ip"     => Utils::getRealIpAddr(),
                "user"          => ( $this->userIsLogged() ? [
                        "uid"        => $this->getUser()->getUid(),
                        "email"      => $this->getUser()->getEmail(),
                        "first_name" => $this->getUser()->getFirstName(),
                        "lat_name"   => $this->getUser()->getLastName()
                ] : [ "uid" => 0 ] ),
                "custom_object" => (object)$this->timingCustomObject,
                "browser"       => Utils::getBrowser(),
                "request_uri"   => $_request_uri,
                "Total Time"    => $this->getTimer()
        ];

        Log::doJsonLog( $object );

    }

}