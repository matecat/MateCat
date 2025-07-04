<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 03/03/17
 * Time: 20.00
 *
 */

namespace Controller\API\V2;


use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Exception;
use Model\Exceptions\NotFoundException;
use Model\Projects\ProjectDao;
use ProjectQueue\Queue;
use View\API\V2\Json\CreationStatus;
use View\API\V2\Json\WaitCreation;

class ProjectCreationStatusController extends KleinController {

    /**
     * @throws Exception
     */
    public function get() {

        // validate id_project
        if ( !is_numeric( $this->request->param( 'id_project' ) ) ) {
            throw new Exception( "ID project is not a valid integer", -1 );
        }

        $result = Queue::getPublishedResults( $this->request->param( 'id_project' ) );

        if ( empty( $result ) ) {

            $this->_letsWait();

        } elseif ( !empty( $result[ 'errors' ] ) ) {

            foreach ( $result[ 'errors' ] as $error ) {
                throw new Exception( $error[ 'message' ], (int)$error[ 'code' ] );
            }

        } else {

            // project is created, find it with password
            try {
                $project = ProjectDao::findByIdAndPassword( $this->request->param( 'id_project' ), $this->request->param( 'password' ) );
            } catch ( NotFoundException $e ) {
                throw new AuthorizationError( 'Not Authorized.' );
            }

            $featureSet = $project->getFeaturesSet();
            $result     = $featureSet->filter( 'filterCreationStatus', $result, $project );

            if ( empty( $result[ 'id_project' ] ) ) {
                $this->_letsWait();
            } else {
                $result = (object)$result;
                $this->response->json( ( new CreationStatus( $result ) )->render() );
            }
        }
    }


    protected function _letsWait() {
        $this->response->code( 202 );
        $this->response->json( ( new WaitCreation() )->render() );
    }
}