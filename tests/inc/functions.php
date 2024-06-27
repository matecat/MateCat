<?php

function sig_handler( $signo ) {

    echo "\n\033[41m" . str_pad( "Caught signal \033[1m$signo", 39, " ", STR_PAD_BOTH ) . "\033[0m\n";
    switch ( $signo ) {
        case SIGHUP:
        case SIGTERM:
        case SIGINT:
            // handle shutdown tasks
            exit;
        default:
            // handle all other signals
    }
}

function setupSignalHandler() {
    pcntl_signal( SIGTERM, "sig_handler" );
    pcntl_signal( SIGHUP, "sig_handler" );
    pcntl_signal( SIGINT, "sig_handler" );
    echo "\033[0;30;42m" . str_pad( "Signal handler installed.", 35, " ", STR_PAD_BOTH ) . "\033[0m\n";
}

/**
 * We are not inside a TestUnit, we can't simply invoke
 *
 * <code>
 *     $this->getMockBuilder('\AMQHandler')->getMock()
 * </code>
 *
 * We have to manually instantiate a MockObject Generator
 *
 * @return void
 */
function disableAmqWorkerClientHelper() {
    WorkerClient::$_HANDLER = @( new PHPUnit_Framework_MockObject_Generator() )->getMock(
            AMQHandler::class,
            [], [], '', false
    );
}
