<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 09/02/17
 * Time: 20.14
 *
 */

namespace API\V2;


use API\V2\Exceptions\AuthorizationError;
use API\V2\Json\Error;
use InvalidArgumentException;
use Organizations\MembershipDao;
use Organizations\WorkspaceDao;
use Organizations\WorkspaceOptionsStruct;
use Organizations\WorkspaceStruct;

class WorkspacesController extends KleinController {

    public function create() {

        $wSpaceDao = new WorkspaceDao();

        $wSpaceStruct                  = new WorkspaceStruct();
        $wSpaceStruct->name            = $this->request->name;
        $wSpaceStruct->id_organization = $this->request->id_organization;

        $options = ( empty( $this->request->options ) ? [] : $this->request->options );
        $wSpaceStruct->options         = new WorkspaceOptionsStruct( $options );

        try {

            if( empty( $wSpaceStruct->name ) ){
                throw new InvalidArgumentException( "Wrong parameter :name ", 400 );
            }

            $membershipDao = new MembershipDao();
            $org = $membershipDao->findOrganizationByIdAndUser( $wSpaceStruct->id_organization, $this->user );
            if ( empty( $org ) ) {
                throw new AuthorizationError( "Not Authorized", 401 );
            }

            $wSpaceDao->create( $wSpaceStruct );

            $this->response->json( [ 'organization' => $wSpaceStruct ] );

        } catch ( \PDOException $e ){
            $this->response->code( 503 );
            $this->response->json( ( new Error( [ $e ] ) )->render() );
        } catch( AuthorizationError $e ){
            $this->response->code( 401 );
            $this->response->json( ( new Error( [ $e ] ) )->render() );
        } catch( InvalidArgumentException $e ){
            $this->response->code( 400 );
            $this->response->json( ( new Error( [ $e ] ) )->render() );
        }


    }

    public function show(){

        $wSpaceDao = new WorkspaceDao();
        $workSpacesList = $wSpaceDao->getByOrganizationId( $this->request->id_organization );
        $this->response->json( [ 'workspaces' => $workSpacesList ] );

    }

    public function update(){

    }

    public function delete(){

    }

}