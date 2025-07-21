<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 02/05/19
 * Time: 19.12
 *
 */

namespace Controller\Traits;


use Controller\Abstracts\IController;
use Utils\Logger\Log;
use Utils\Tools\Utils;

trait TimeLoggerTrait {

    protected string $timingLogFileName  = 'ui_calls_time.log';
    protected array  $timingCustomObject = [];
    protected int    $startExecutionTime = 0;

    protected function startTimer() {
        $this->startExecutionTime = microtime( true );
    }

    public function getTimer(): float {
        return round( microtime( true ) - $this->startExecutionTime, 4 ); //get milliseconds
    }

    /**
     * @return void
     */
    protected function logPageCall() {

        Log::setLogFileName( $this->timingLogFileName );

        /** @var $this IController|TimeLoggerTrait */

        $_request_uri = parse_url( $_SERVER[ 'REQUEST_URI' ] );
        if ( isset( $_request_uri[ 'query' ] ) ) {
            parse_str( $_request_uri[ 'query' ], $str );
            $_request_uri[ 'query' ] = $str;
        }

        $object = [
                "client_ip"     => Utils::getRealIpAddr(),
                "user"          => ( $this->isLoggedIn() ? [
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