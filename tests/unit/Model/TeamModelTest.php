<?php

use Teams\TeamStruct;
use TestHelpers\AbstractTest;

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 16/02/2017
 * Time: 10:08
 */
class TeamModelTest extends AbstractTest {

    /**
     * TODO: remove this test once we are able to test email
     * delivery at higher level.
     *
     */
    function test_notify_list_for_new_membership_is_correct() {
        $user       = Factory_User::create();
        $other_user = Factory_User::create();

        $newOrg = new TeamStruct( [
                'name'       => 'test team',
                'created_by' => $user->uid,
                'type'       => Constants_Teams::GENERAL
        ] );

        $teamModel = new TeamModel( $newOrg );
        $teamModel->setUser( $user );

        $teamModel->addMemberEmail( Factory_User::getNewUser()->getEmail() );
        $teamModel->addMemberEmail( Factory_User::getNewUser()->getEmail() );
        $teamModel->addMemberEmail( $other_user->email );

        $teamModel->create();

        $reflection = new ReflectionClass( $teamModel );

        $method = $reflection->getMethod( '_getNewMembershipEmailList' );
        $method->setAccessible( true );
        $notify_list = $method->invoke( $teamModel );

        $this->assertEquals( 1, count( $notify_list ) );
        $this->assertEquals( $other_user->email, $notify_list[ 0 ]->getUser()->email );
    }

}