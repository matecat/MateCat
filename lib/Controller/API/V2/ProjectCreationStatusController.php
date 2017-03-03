<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 03/03/17
 * Time: 20.00
 *
 */

namespace API\V2;


use API\V2\Json\CreationStatus;
use API\V2\Json\Error;
use Exception;
use ProjectQueue\Queue;

class ProjectCreationStatusController extends KleinController {

    public function get(){

        $result = Queue::getPublishedResults( $this->request->id_project );
        if( !empty( $result[ 'errors' ] ) ){

            $response = [];
            foreach( $result[ 'errors' ] as $error ){
                $response[] = new Exception( $error[ 'debug' ], $error[ 'code' ] );
            }

            $this->response->json( ( new Error( [ (object)$result ] ) )->render() );

        } else {

            $this->response->json( ( new CreationStatus( [ (object)$result ] ) )->render() );

        }

    }

}