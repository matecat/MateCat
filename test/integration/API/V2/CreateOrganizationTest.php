<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 15/02/2017
 * Time: 12:22
 */
class CreateOrganizationTest extends IntegrationTest {

    function setup() {
        $this->test_data = new stdClass();
        $this->prepareUserAndApiKey();
    }

    public function test_create_organization_with_members() {
        $new_member = Factory_User::create() ;
        // create an organization for the user

        $organizationRequest = new CurlTest();
        $organizationRequest->headers = $this->test_data->headers ;
        $organizationRequest->path = '/api/v2/orgs' ;
        $organizationRequest->method = 'POST' ;
        $organizationRequest->params = [
            'type'    => Constants_Organizations::GENERAL,
            'name'    => 'New organization',
            'members' => [ $new_member->email, 'bar@example.org']
        ];

        $response = json_decode( $organizationRequest->getResponse()['body'], true );
        $members = ( new \Organizations\MembershipDao() )->getMemberListByOrganizationId( $response['organization']['id'] ) ;

        /**
         * Ensure new_member is among organization's members
         */
        $found = array_values( array_filter($members, function(\Organizations\MembershipStruct $member) use ( $new_member ) {
            return $new_member->uid == $member->getUser()->uid;
        }));

        $this->assertEquals( $new_member->email, $found[0]->getUser()->email ) ;
    }
}
