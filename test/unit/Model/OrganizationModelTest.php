<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 16/02/2017
 * Time: 10:08
 */
class OrganizationModelTest extends PHPUnit_Framework_TestCase
{

    /**
     * TODO: remove this test once we are able to test email
     * delivery at higher level.
     *
     */
    function test_notify_list_for_new_membership_is_correct() {
        $user = Factory_User::create() ;
        $other_user = Factory_User::create() ;

        $newOrg = new \Organizations\OrganizationStruct(array(
            'name'       => 'test organization',
            'created_by' => $user->uid,
            'type'       => Constants_Organizations::GENERAL
        )) ;

        $organizationModel = new OrganizationModel($newOrg) ;
        $organizationModel->setUser( $user ) ;

        $organizationModel->addMemberEmail('foo@example.org') ;
        $organizationModel->addMemberEmail('bar@example.org') ;
        $organizationModel->addMemberEmail( $other_user->email ) ;

        $organizationModel->create();

        $reflection = new ReflectionClass( $organizationModel );

        $method = $reflection->getMethod('getNewMembershipEmailList') ;
        $method->setAccessible(true);
        $notify_list = $method->invoke( $organizationModel ) ;

        $this->assertEquals( 1, count( $notify_list ) ) ;
        $this->assertEquals( $other_user->email, $notify_list[0]->getUser()->email );
    }

}