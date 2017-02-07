<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 07/02/17
 * Time: 12.12
 *
 */

namespace API\V2;

use API\V2\Json\Error;
use Organizations\MembershipDao;

class OrganizationMembersController extends KleinController {

    /**
     * Get organization members list
     */
    public function index(){

        try{

            $membersList = ( new MembershipDao )->getMemberListByOrganizationId( $this->request->id_organization );
            $this->response->json( array( 'members_list' => $membersList ) );

        } catch ( \PDOException $e ){
            $this->response->code( 503 );
            $this->response->json( ( new Error( [ $e ] ) )->render() );
        }

    }

    public function update(){
//        try{
//
//            $membersList = ( new MembershipDao )->getMemberListByOrganizationId( $this->request->id_organization );
//            $this->response->json( array( 'members_list' => $membersList ) );
//
//        } catch ( \PDOException $e ){
//            $this->response->code( 503 );
//            $this->response->json( ( new Error( [ $e ] ) )->render() );
//        }
    }

    public function delete(){

    }

}