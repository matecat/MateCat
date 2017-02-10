<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 06/02/17
 * Time: 13.01
 *
 */

namespace API\V2;


use API\V2\Exceptions\AuthorizationError;
use API\V2\Json\Organization;
use Constants_Organizations;
use InvalidArgumentException;
use Organizations\MembershipDao;
use Organizations\OrganizationDao;
use API\V2\Json\Error;
use Organizations\OrganizationStruct;

class OrganizationsController extends KleinController {

    public function create() {

        $teamDao = new OrganizationDao();

        try{

            if ( !Constants_Organizations::isAllowedType( $this->request->type ) ) {
                $type = Constants_Organizations::PERSONAL;
                if ( $teamDao->getPersonalByUid( $this->user->uid ) ) {
                    throw new InvalidArgumentException( "User already has the personal organization" );
                }
            } else {
                $type = strtolower( $this->request->type );
            }

            \Database::obtain()->begin();
            $organization = $teamDao->createUserOrganization( $this->user, [
                    'type'    => $type,
                    'name'    => $this->request->name,  //name can not be null, PDOException
                    'members' => $this->request->members
            ] );
            \Database::obtain()->commit();
            ( new MembershipDao() )->destroyCacheUserOrganizations( $this->user ); // clean the cache

            //TODO sent an email to the $params[ 'members' ] ( warning, not all members are registered users )

            $formatted = new Organization( [ $organization ] ) ;
            $this->response->json( array( 'organization' => $formatted->render() ) );

        } catch ( \PDOException $e ){
            \Database::obtain()->rollback();
            $this->response->code( 400 );
            $this->response->json( ( new Error( [ $e ] ) )->render() );
        } catch( InvalidArgumentException $e ){
            $this->response->code( 400 );
            $this->response->json( ( new Error( [ $e ] ) )->render() );
        }

    }

    public function update() {

        $requestContent = json_decode( file_get_contents( 'php://input' ) );


        try {

            $org = new OrganizationStruct();
            $org->id = $this->request->id_organization;

            $org->name = $requestContent->name;
            if( empty( $org->name ) ){
                throw new InvalidArgumentException( "Wrong parameter :name ", 400 );
            }

            $membershipDao = new MembershipDao();
            $org = $membershipDao->findOrganizationByIdAndUser( $org->id, $this->user );

            if( empty( $org ) ){
                throw new AuthorizationError( "Not Authorized", 401 );
            }

            $teamDao = new OrganizationDao();

            $teamDao->updateOrganizationName( $org );
            ( new MembershipDao() )->destroyCacheUserOrganizations( $this->user ); // clean the cache

            $formatted = new Organization( [ $org ] ) ;
            $this->response->json( [ 'organization' => $formatted->render() ] );
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

}