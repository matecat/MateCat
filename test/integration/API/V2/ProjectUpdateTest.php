<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/02/2017
 * Time: 17:20
 */

class ProjectUpdateTest extends IntegrationTest  {

    protected $test_data ;

    public function setUp()
    {
        $this->test_data = new stdClass();
    }

    /**
     * Assignee
     */

    public function test_anonymous_project_has_no_assignee() {
        $project_body = integrationCreateTestProject();
        $project = Projects_ProjectDao::findById( $project_body->id_project );

        $this->assertEquals(null, $project->id_assignee );

    }

    public function test_assignee_can_be_set() {
        $this->prepareUserAndApiKey();

        $project_body = integrationCreateTestProject(array(
            'headers' => $this->test_data->headers
            ) );

        $project = Projects_ProjectDao::findById( $project_body->id_project);

        $other_user = Factory_User::create() ;
        ( new \Organizations\MembershipDao() )->createList(array(
            'organization' => $this->test_data->user->getPersonalOrganization(),
            'members' => array( $other_user->email )
        ) ) ;

        /**
         * @var $user Users_UserStruct
         */
        $user = $this->test_data->user ;

        $this->assertEquals($project->id_customer, $user->email ) ;

        $this->assertEquals( $user->getPersonalOrganization()->id, $project->id_organization  );

        $test = new CurlTest(array(
            'headers' => $this->test_data->headers,
            'path' => "/api/v2/orgs/{$project->id_organization}/projects/{$project->id}",
            'method' => 'PUT',
            'params' => array('id_assignee' => $other_user->uid )
        ) ) ;

        $response = $test->getResponse() ;

        $project = Projects_ProjectDao::findById( $project->id ) ;
        $this->assertEquals($project->id_assignee, $other_user->uid ) ;

    }
      public function test_project_name_can_be_changed() {
        $this->prepareUserAndApiKey();

        $project_body = integrationCreateTestProject(array(
            'headers' => $this->test_data->headers
            ) );

        $project = Projects_ProjectDao::findById( $project_body->id_project);

        $other_user = Factory_User::create() ;
        ( new \Organizations\MembershipDao() )->createList(array(
            'organization' => $this->test_data->user->getPersonalOrganization(),
            'members' => array( $other_user->email )
        ) ) ;

        /**
         * @var $user Users_UserStruct
         */
        $user = $this->test_data->user ;

        $this->assertEquals($project->id_customer, $user->email ) ;
        $this->assertEquals( $user->getPersonalOrganization()->id, $project->id_organization  );

        $test = new CurlTest(array(
            'headers' => $this->test_data->headers,
            'path' => "/api/v2/orgs/{$project->id_organization}/projects/{$project->id}",
            'method' => 'PUT',
            'params' => array('name' => 'My new project name' )
        ) ) ;

        $response = $test->getResponse() ;

        $project = Projects_ProjectDao::findById( $project->id ) ;
        $this->assertEquals('My new project name', $project->name ) ;

    }

}