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
use API\V2\Json\Error;
use API\V2\Json\Team;
use API\V2\Validators\LoginValidator;
use API\V2\Validators\TeamAccessValidator;
use InvalidArgumentException;
use Teams\MembershipDao;
use Teams\TeamDao;
use Teams\TeamStruct;

class TeamsController extends KleinController {

    protected function afterConstruct() {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    protected function addValidatorAccess() {
        $this->appendValidator( new TeamAccessValidator( $this ) );
    }

    public function create() {

        $params = $this->request->paramsPost()->getIterator()->getArrayCopy();

        $params = filter_var_array( $params, [
                'name'    => [
                        'filter' => FILTER_SANITIZE_STRING,
                        'flags'  => FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_NO_ENCODE_QUOTES | FILTER_FLAG_STRIP_BACKTICK
                ],
                'type'    => [
                        'filter' => FILTER_SANITIZE_STRING
                ],
                'members' => [
                        'filter' => FILTER_SANITIZE_EMAIL,
                        'flags'  => FILTER_REQUIRE_ARRAY
                ]
        ], true );

        $teamStruct = new TeamStruct( array(
                'created_by' => $this->user->uid,
                'name'       => $params[ 'name' ],
                'type'       => $params[ 'type' ]
        ) );

        $model = new \TeamModel( $teamStruct );
        foreach ( $params[ 'members' ] as $email ) {
            $model->addMemberEmail( $email );
        }
        $model->setUser( $this->user );

        $team      = $model->create();
        $formatted = new Team();

        $this->response->json( [ 'team' => $formatted->renderItem( $team ) ] );
    }

    public function update() {

        $this->addValidatorAccess();
        $this->validateRequest();

        try {

            // sanitize params
            $params = filter_var_array( $this->params, [
                'name'    => [
                    'filter' => FILTER_SANITIZE_STRING,
                    'flags'  => FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_NO_ENCODE_QUOTES | FILTER_FLAG_STRIP_BACKTICK
                ],
                'id_team'    => [
                    'filter' => FILTER_VALIDATE_INT
                ],
            ], true );

            $org       = new TeamStruct();
            $org->id   = $params['id_team'];
            $org->name = $params[ 'name' ];

            if ( empty( $org->name ) ) {
                throw new InvalidArgumentException( "Wrong parameter :name ", 400 );
            }

            $membershipDao = new MembershipDao();
            $org           = $membershipDao->findTeamByIdAndUser( $org->id, $this->user );

            if ( empty( $org ) ) {
                throw new AuthorizationError( "Not Authorized", 401 );
            }

            $org->name = $params[ 'name' ];

            $teamDao = new TeamDao();

            $teamDao->updateTeamName( $org );
            $memberList = ( new MembershipDao() )->getMemberListByTeamId( $org->id );

            foreach ( $memberList as $user ) {
                ( new MembershipDao() )->destroyCacheUserTeams( $user->getUser() ); // clean the cache for all team users to see the changes
            }

            $formatted = new Team( [ $org ] );
            $this->response->json( [ 'team' => $formatted->render() ] );
        } catch ( \PDOException $e ) {
            $this->response->code( 503 );
            $this->response->json( ( new Error( [ $e ] ) )->render() );
        } catch ( AuthorizationError $e ) {
            $this->response->code( 401 );
            $this->response->json( ( new Error( [ $e ] ) )->render() );
        } catch ( InvalidArgumentException $e ) {
            $this->response->code( 400 );
            $this->response->json( ( new Error( [ $e ] ) )->render() );
        }


    }

    public function getTeamList(){

        $teamList = ( new MembershipDao() )->findUserTeams( $this->user );
        $formatted = new Team( $teamList );
        $this->response->json( [ 'teams' => $formatted->render() ] );

    }

}