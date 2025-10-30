<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 06/02/17
 * Time: 13.01
 *
 */

namespace Controller\API\V2;


use Controller\Abstracts\KleinController;
use Controller\API\Commons\Exceptions\AuthorizationError;
use Controller\API\Commons\Validators\LoginValidator;
use Controller\API\Commons\Validators\TeamAccessValidator;
use Exception;
use InvalidArgumentException;
use Model\Teams\MembershipDao;
use Model\Teams\TeamDao;
use Model\Teams\TeamModel;
use Model\Teams\TeamStruct;
use ReflectionException;
use Utils\Constants\Teams;
use View\API\V2\Json\Team;

class TeamsController extends KleinController {

    protected function afterConstruct(): void {
        $this->appendValidator( new LoginValidator( $this ) );
    }

    protected function addValidatorAccess() {
        $this->appendValidator( new TeamAccessValidator( $this ) );
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function create(): void {

        $params = $this->request->paramsPost()->getIterator()->getArrayCopy();

        $params = filter_var_array( $params, [
                'name'    => [
                        'filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                        'flags'  => FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_NO_ENCODE_QUOTES | FILTER_FLAG_STRIP_BACKTICK
                ],
                'type'    => [
                        'filter' => FILTER_SANITIZE_SPECIAL_CHARS
                ],
                'members' => [
                        'filter' => FILTER_SANITIZE_EMAIL,
                        'flags'  => FILTER_REQUIRE_ARRAY
                ]
        ] );

        $params[ 'name' ] = trim( $params[ 'name' ] );

        if ( empty( $params[ 'name' ] ) ) {
            throw new InvalidArgumentException( "Wrong parameter: name is empty", 400 );
        }

        if ( empty( $params[ 'type' ] ) ) {
            throw new InvalidArgumentException( "Wrong parameter: type is empty", 400 );
        }

        if ( !in_array( $params[ 'type' ], [ Teams::GENERAL, Teams::PERSONAL ] ) ) {
            throw new InvalidArgumentException( "Wrong parameter: type is not allowed [Allowed values: personal, general]", 400 );
        }

        $teamStruct = new TeamStruct( [
                'created_by' => $this->user->uid,
                'name'       => $params[ 'name' ],
                'type'       => $params[ 'type' ]
        ] );

        $model = new TeamModel( $teamStruct );
        foreach ( $params[ 'members' ] as $email ) {
            $model->addMemberEmail( $email );
        }
        $model->setUser( $this->user );

        $team      = $model->create();
        $formatted = new Team();

        $this->refreshClientSessionIfNotApi();

        $this->response->json( [ 'team' => $formatted->renderItem( $team ) ] );
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function update(): void {

        $this->addValidatorAccess();
        $this->validateRequest();

        // sanitize params
        $params = filter_var_array( $this->params, [
                'name'    => [
                        'filter' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                        'flags'  => FILTER_FLAG_ENCODE_LOW | FILTER_FLAG_NO_ENCODE_QUOTES | FILTER_FLAG_STRIP_BACKTICK
                ],
                'id_team' => [
                        'filter' => FILTER_VALIDATE_INT
                ],
        ] );

        $org       = new TeamStruct();
        $org->id   = $params[ 'id_team' ];
        $org->name = trim( $params[ 'name' ] );

        if ( empty( $org->name ) ) {
            throw new InvalidArgumentException( "Wrong parameter: name is empty", 400 );
        }

        $membershipDao = new MembershipDao();
        $org           = $membershipDao->findTeamByIdAndUser( $org->id, $this->user );

        if ( empty( $org ) ) {
            throw new AuthorizationError( "Not Authorized", 401 );
        }

        $org->name = trim( $params[ 'name' ] );

        $teamDao = new TeamDao();

        $teamDao->updateTeamName( $org );
        $memberList = ( new MembershipDao() )->getMemberListByTeamId( $org->id );

        foreach ( $memberList as $user ) {
            ( new MembershipDao() )->destroyCacheUserTeams( $user->getUser() ); // clean the cache for all team users to see the changes
        }

        $formatted = new Team( [ $org ] );

        $this->refreshClientSessionIfNotApi();

        $this->response->json( [ 'team' => $formatted->render() ] );

    }

    /**
     * @throws ReflectionException
     */
    public function getTeamList(): void {

        $teamList  = ( new MembershipDao() )->findUserTeams( $this->user );
        $formatted = new Team( $teamList );
        $this->response->json( [ 'teams' => $formatted->render() ] );

    }

}