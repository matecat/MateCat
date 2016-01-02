<?php

namespace Analysis;

use \INIT,
    \Exception,
    \AMQHandler,
    \MultiCurlHandler,
    \DQF_DqfProjectStruct,
    \DQF_DqfSegmentStruct,
    \DQF_DqfTaskStruct;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 18/05/15
 * Time: 13.15
 */
class DqfQueueHandler extends AMQHandler {

    public function __construct( $brokerUri = null ) {

        if ( !is_null( $brokerUri ) ) {
            parent::__construct( $brokerUri );
        } else {
            parent::__construct( INIT::$QUEUE_DQF_ADDRESS );
        }

    }

    /**
     * @param null $queueName
     *
     * @return bool
     */
    public function subscribe( $queueName = null ) {

        if ( empty( $queueName ) ) {
            $queueName = INIT::$DQF_PROJECTS_TASKS_QUEUE_NAME;
        }

        return parent::subscribe( $queueName );
    }

    /**
     * @param DQF_DqfProjectStruct $data
     *
     * @return bool
     * @throws Exception
     */
    public function createProject( DQF_DqfProjectStruct $data ) {
        $data = json_encode( $data );

        if ( $data == false ) {
            throw new Exception ( "Failed on json_encode" );
        }

        return $this->send( INIT::$DQF_PROJECTS_TASKS_QUEUE_NAME, $data, array( 'persistent' => $this->persistent ) );
    }

    /**
     * @param DQF_DqfTaskStruct $data
     *
     * @return bool
     * @throws Exception
     */
    public function createTask( DQF_DqfTaskStruct $data ) {
        $data = json_encode( $data );

        if ( $data == false ) {
            throw new Exception ( "Failed on json_encode" );
        }

        return $this->send( INIT::$DQF_PROJECTS_TASKS_QUEUE_NAME, $data, array( 'persistent' => $this->persistent ) );
    }

    /**
     * @param DQF_DqfSegmentStruct $data
     *
     * @return bool
     * @throws Exception
     */
    public function createSegment( DQF_DqfSegmentStruct $data ) {
        $data = json_encode( $data );

        if ( $data == false ) {
            throw new Exception ( "Failed on json_encode" );
        }

        return $this->send( INIT::$DQF_SEGMENTS_QUEUE_NAME, $data, array( 'persistent' => $this->persistent ) );
    }

    /**
     * @param $PM_KEY
     *
     * @return mixed
     * @throws Exception
     */
    public function checkProjectManagerKey( $PM_KEY ){

        $options = array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT      => INIT::MATECAT_USER_AGENT . INIT::$BUILD_NUMBER,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER     => array( "DQF_PMANAGER_KEY: $PM_KEY" )
        );

        $cHandler = new MultiCurlHandler();
        $hash = $cHandler->createResource( "https://dqf.taus.net/api/v1/projectmanager/", $options );
        $cHandler->setRequestHeader( $hash )->multiExec();

        $result = json_decode( $cHandler->getSingleContent( $hash ) );

        if( isset( $result->code ) && $result->code != 200 ){
            throw new Exception( $result->message );
        }

        return $result;

    }

}