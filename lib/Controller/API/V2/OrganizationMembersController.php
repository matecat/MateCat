<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 07/02/17
 * Time: 12.12
 *
 */

namespace API\V2;

use API\V2\Exceptions\AuthorizationError;
use API\V2\Json\Error;
use Organizations\MembershipDao;
use Organizations\OrganizationDao;

class OrganizationMembersController extends KleinController {

    /**
     * Get organization members list
     */
    public function index(){

        try{

            $membersList = ( new MembershipDao )->setCacheTTL( 60 * 60 * 24 )->getMemberListByOrganizationId( $this->request->id_organization );
            $this->response->json( array( 'members' => $membersList ) );

        } catch ( \PDOException $e ){
            $this->response->code( 503 );
            $this->response->json( ( new Error( [ $e ] ) )->render() );
        }

    }

    public function update(){
        try{

            $membershipDao = new MembershipDao();
            $org = $membershipDao->findOrganizationByIdAndUser( $this->request->id_organization, $this->user );

            if( empty( $org ) ){
                throw new AuthorizationError( "Not Authorized", 401 );
            }

            \Database::obtain()->begin();
            $organizationStruct = ( new OrganizationDao() )->findById( $this->request->id_organization );
            ( new MembershipDao )->createList( [
                    'organization' => $organizationStruct,
                    'members' => $this->request->members
            ] );
            ( new MembershipDao )->destroyCacheForListByOrganizationId( $organizationStruct->id );
            $membersList = ( new MembershipDao )->setCacheTTL( 60 * 60 * 24 )->getMemberListByOrganizationId( $organizationStruct->id );
            \Database::obtain()->commit();

            $this->response->json( array( 'members' => $membersList ) );

        } catch ( \PDOException $e ){
            $this->response->code( 503 );
            $this->response->json( ( new Error( [ $e ] ) )->render() );
        } catch( AuthorizationError $e ){
            $this->response->code( 401 );
            $this->response->json( ( new Error( [ $e ] ) )->render() );
        }

    }

    public function delete(){

        try{

            $membershipDao = new MembershipDao();
            $org = $membershipDao->findOrganizationByIdAndUser( $this->request->id_organization, $this->user );

            if( empty( $org ) ){
                throw new AuthorizationError( "Not Authorized", 401 );
            }

            \Database::obtain()->begin();
            $membershipDao->deleteUserFromOrganization( $this->request->uid_member, $this->request->id_organization );
            $membershipDao->destroyCacheForListByOrganizationId( $this->request->id_organization );
            $membersList = $membershipDao->setCacheTTL( 60 * 60 * 24 )->getMemberListByOrganizationId( $this->request->id_organization );
            \Database::obtain()->commit();

            $this->response->json( array( 'members' => $membersList ) );

        } catch ( \PDOException $e ){
            $this->response->code( 503 );
            $this->response->json( ( new Error( [ $e ] ) )->render() );
        } catch( AuthorizationError $e ){
            $this->response->code( 401 );
            $this->response->json( ( new Error( [ $e ] ) )->render() );
        }

    }

}