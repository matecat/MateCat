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

    protected function getTimer() {
        return round( microtime( true ) - $this->startExecutionTime, 4 ); //get milliseconds
    }

    protected function logPageCall() {

        if ( !$this->userIsLogged() && $this instanceof \controller ) {
            $this->readLoginInfo( true );
        }

        Log::$fileName = $this->timingLogFileName;

        /** @var $this IController|TimeLogger */

        $object = [
                "context"       => get_class( $this ),
                "client_ip"     => Utils::getRealIpAddr(),
                "user_token"    => \Log::$uniqID,
                "user"          => ( $this->userIsLogged() ? [
                        "uid"        => $this->getUser()->getUid(),
                        "email"      => $this->getUser()->getEmail(),
                        "first_name" => $this->getUser()->getFirstName(),
                        "lat_name"   => $this->getUser()->getLastName()
                ] : [ "uid" => 0 ] ),
                "custom_object" => $this->timingCustomObject,
                "browser"       => Utils::getBrowser(),
                "request_uri"   => $_SERVER[ 'REQUEST_URI' ],
                "time"          => time(),
                "Total Time"    => $this->getTimer()
        ];

        Log::doLogRaw( json_encode( $object ) );

    }

}