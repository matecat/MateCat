<?php
use Teams\MembershipDao;
use Teams\MembershipStruct;

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 15/02/2017
 * Time: 12:22
 */
class CreateTeamTest extends IntegrationTest {

    function setup() {
        $this->test_data = new stdClass();
        $this->prepareUserAndApiKey();
    }

    public function test_create_team_with_members() {
        $new_member = Factory_User::create();
        // create an team for the user

        $teamRequest          = new CurlTest();
        $teamRequest->headers = $this->test_data->headers;
        $teamRequest->path    = '/api/v2/teams';
        $teamRequest->method  = 'POST';
        $teamRequest->params  = [
                'type'    => Constants_Teams::GENERAL,
                'name'    => 'New team',
                'members' => [ $new_member->email, 'bar@example.org' ]
        ];

        $response = json_decode( $teamRequest->getResponse()[ 'body' ], true );
        $members  = ( new MembershipDao() )->getMemberListByTeamId( $response[ 'team' ][ 'id' ] );

        /**
         * Ensure new_member is among team's members
         */
        $found = array_values( array_filter( $members, function ( MembershipStruct $member ) use ( $new_member ) {
            return $new_member->uid == $member->getUser()->uid;
        } ) );

        $this->assertEquals( $new_member->email, $found[ 0 ]->getUser()->email );
    }
}
