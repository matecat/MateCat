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
use Exceptions\NotFoundException;
use ProjectQueue\Queue;
use Projects_ProjectDao;

class ProjectCreationStatusController extends KleinController {

    public function get(){

        // validate id_project
        if(!is_numeric($this->request->id_project)){

            $response = [];
            $response[] = new Exception( "ID project is not a valid integer", -1 );

            $this->response->code( 400 );
            $this->response->json( ( new Error( (object)$response ) )->render() );
            exit();
        }

        $result = Queue::getPublishedResults( $this->request->id_project );

        if ( empty( $result ) ) {

            $this->_letsWait();

        } elseif ( !empty( $result ) && !empty( $result[ 'errors' ] ) ){

            $response = [];
            foreach( $result[ 'errors' ] as $error ){
                $response[] = new Exception( $error[ 'message' ], (int)$error[ 'code' ] );
            }

            $this->response->code( 500 );
            $this->response->json( ( new Error( (object)$response ) )->render() );

        } else {


            // project is created, find it with password
            try {
                $project = Projects_ProjectDao::findByIdAndPassword($this->request->id_project, $this->request->password ) ;
            } catch( NotFoundException $e ) {
                throw new AuthorizationError( 'Not Authorized.' );
            }

            $featureSet = $project->getFeaturesSet();
            $result = $featureSet->filter('filterCreationStatus', $result, $project);

            if ( empty( $result ) ) {
                $this->_letsWait();
            }
            else {
                $result = (object)$result;
                $this->response->json( ( new CreationStatus( (object)$result ) )->render() );
            }
        }
    }


    protected function _letsWait() {
        $this->response->code( 202 );
        $this->response->json( ( new WaitCreation() )->render() );
    }
}