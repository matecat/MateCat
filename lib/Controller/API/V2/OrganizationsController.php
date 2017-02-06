<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 06/02/17
 * Time: 13.01
 *
 */

namespace API\V2;


use API\V2\Json\Organization;
use Constants_Organizations;
use InvalidArgumentException;
use Organizations\OrganizationDao;
use API\V2\Json\Error;

class OrganizationsController extends KleinController {

    /**
     * @var \Projects_ProjectStruct
     */
    protected $project;

    public function create() {

        $teamDao = new OrganizationDao();

        if ( !Constants_Organizations::isAllowedType( $this->request->type ) ) {
            $type = Constants_Organizations::PERSONAL;
            if ( $teamDao->getPersonalByUid( $this->user->uid ) ) {
                throw new InvalidArgumentException( "User already has the personal organization" );
            }
        } else {
            $type = strtolower( $this->request->type );
        }

        try{

            $organizationStruct = $teamDao->createUserOrganization( $this->user, [
                    'type' => $type,
                    'name' => $this->request->name  //name can not be null, PDOException
            ] );

            $formatted = new Organization( [ $organizationStruct ] ) ;
            $this->response->json( array( 'organization' => $formatted->render() ) );

        } catch ( \PDOException $e ){
            $this->response->code( 400 );
            $this->response->json( ( new Error( [ $e ] ) )->render() );
        }

    }

}