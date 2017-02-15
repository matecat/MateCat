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
use API\V2\Json\Workspace;
use API\V2\Validators\OrganizationAccessValidator;
use InvalidArgumentException;
use Organizations\WorkspaceDao;
use Organizations\WorkspaceOptionsStruct;
use Organizations\WorkspaceStruct;

class WorkspacesController extends KleinController {

    public function afterConstruct() {
        $this->appendValidator( new OrganizationAccessValidator( $this ) );
    }

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

            $wSpaceDao->create( $wSpaceStruct );
            $wSpaceDao->destroyCacheForOrganizationId( $this->request->id_organization ); //clean the cache

            $this->response->json( [ 'workspace' => Workspace::renderItem( $wSpaceStruct ) ] );

        } catch ( \PDOException $e ){
            $this->response->code( 503 );
            $this->response->json( ( new Error( [ $e ] ) )->render() );
        } catch( InvalidArgumentException $e ){
            $this->response->code( 400 );
            $this->response->json( ( new Error( [ $e ] ) )->render() );
        }

    }

    public function show(){

        $wSpaceDao = new WorkspaceDao();

        try {

            $workSpacesList = $wSpaceDao->setCacheTTL( 60 * 60 * 24 )->getByOrganizationId( $this->request->id_organization );
            $formatter = new Workspace();

            $this->response->json( [ 'workspaces' => $formatter->render( $workSpacesList ) ] );

        } catch( AuthorizationError $e ){
            $this->response->code( 401 );
            $this->response->json( ( new Error( [ $e ] ) )->render() );
        }

    }

    public function update() {

        $requestContent = json_decode( file_get_contents( 'php://input' ) );

        try {

            if( empty( $requestContent->name ) ){
                throw new InvalidArgumentException( "Wrong parameter :name ", 400 );
            }

            $wSpaceDao = new WorkspaceDao();
            $wStruct = $wSpaceDao->getById( $this->request->id_workspace );
            $wStruct->name = $requestContent->name;

            $wSpaceDao->update( $wStruct );
            $wSpaceDao->destroyCacheForOrganizationId( $this->request->id_organization ); //clean the cache

            $this->response->json( [ 'workspace' => Workspace::renderItem($wStruct) ] );

        } catch ( \PDOException $e ){
            $this->response->code( 503 );
            $this->response->json( ( new Error( [ $e ] ) )->render() );
        } catch( InvalidArgumentException $e ){
            $this->response->code( 400 );
            $this->response->json( ( new Error( [ $e ] ) )->render() );
        }

    }

    /**
     * //TODO Set NULL to id_workspace in project table for the relative organization
     */
    public function delete(){

        try{

            $workspaceDao = new WorkspaceDao();
            $wStructQuery = new WorkspaceStruct();
            $wStructQuery->id = $this->request->id_workspace;
            \Database::obtain()->begin();
            $workspaceDao->delete( $wStructQuery );
            $workspaceDao->destroyCacheForOrganizationId( $this->request->id_organization );
            $workspacesList = $workspaceDao->setCacheTTL( 60 * 60 * 24 )->getByOrganizationId( $this->request->id_organization );
            \Database::obtain()->commit();

            $this->response->json( array( 'workspaces' => $workspacesList ) );

        } catch ( \PDOException $e ){
            $this->response->code( 503 );
            $this->response->json( ( new Error( [ $e ] ) )->render() );
        }

    }

}