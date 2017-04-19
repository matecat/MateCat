<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 03/03/17
 * Time: 20.00
 *
 */

namespace API\V2;


use API\V2\Exceptions\AuthorizationError;
use API\V2\Json\CreationStatus;
use API\V2\Json\Error;
use API\V2\Json\WaitCreation;
use Exception;
use ProjectQueue\Queue;

class ProjectCreationStatusController extends KleinController {

    public function get(){

        $result = Queue::getPublishedResults( $this->request->id_project );
        if( !empty( $result ) && !empty( $result[ 'errors' ] ) ){

            $response = [];
            foreach( $result[ 'errors' ] as $error ){
                $response[] = new Exception( $error[ 'message' ], $error[ 'code' ] );
            }

            $this->response->code( 500 );
            $this->response->json( ( new Error( (object)$response ) )->render() );

        } elseif( empty( $result ) ){
            $this->response->code( 202 );
            $this->response->json( ( new WaitCreation() )->render() );

        } else {

            $result = (object)$result;

            if( $result->ppassword != $this->request->password ){
                throw new AuthorizationError( 'Not Authorized.' );
            }

            $this->response->json( ( new CreationStatus( (object)$result ) )->render() );

        }

    }

}