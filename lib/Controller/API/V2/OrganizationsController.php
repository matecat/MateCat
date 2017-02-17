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
use API\V2\Validators\OrganizationAccessValidator;
use InvalidArgumentException;
use Organizations\MembershipDao;
use Organizations\OrganizationDao;
use API\V2\Json\Error;
use Organizations\OrganizationStruct;

class OrganizationsController extends KleinController {

    public function create() {

        $params = $this->request->paramsPost()->getIterator()->getArrayCopy();

        $params = filter_var_array($params, [
            'name' => ['filter' => FILTER_SANITIZE_STRING ],
            'type' => ['filter' => FILTER_SANITIZE_STRING ],
            'members' => [
                'filter' => FILTER_SANITIZE_EMAIL,
                'flags' => FILTER_REQUIRE_ARRAY
            ]
        ], true ) ;

        $organizationStruct = new OrganizationStruct(array(
            'created_by' => $this->user->uid,
            'name' => $params['name'],
            'type' => $params['type']
        ) );

        $model = new \OrganizationModel( $organizationStruct );
        foreach( $params['members'] as $email ) {
            $model->addMemberEmail( $email ) ;
        }
        $model->setUser($this->user) ;

        $organization = $model->create();
        $formatted = new Organization() ;

        $this->response->json( array( 'organization' => $formatted->renderItem($organization) ) );
    }

    protected function afterConstruct()
    {
        parent::afterConstruct();
        $this->appendValidator( new OrganizationAccessValidator($this) ) ;
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

            $org->name = $requestContent->name;

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