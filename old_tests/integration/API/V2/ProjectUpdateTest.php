<?php
use Teams\MembershipDao;

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 10/02/2017
 * Time: 17:20
 */
class ProjectUpdateTest extends IntegrationTest {

    protected $test_data;

    public function setUp() {
        $this->test_data = new stdClass();
    }

    /**
     * Assignee
     */

    public function test_anonymous_project_has_no_assignee() {
        $project_body = integrationCreateTestProject();
        $project      = Projects_ProjectDao::findById( $project_body->id_project );

        $this->assertEquals( null, $project->id_assignee );

    }

    public function test_assignee_can_be_set_and_unset() {
        $this->prepareUserAndApiKey();

        $project_body = integrationCreateTestProject( array(
                'headers' => $this->test_data->headers
        ) );

        $project = Projects_ProjectDao::findById( $project_body->id_project );

        $other_user = Factory_User::create();
        ( new MembershipDao() )->createList( array(
                'team'    => $this->test_data->user->getPersonalTeam(),
                'members' => array( $other_user->email )
        ) );

        /**
         * @var $user Users_UserStruct
         */
        $user = $this->test_data->user;

        $this->assertEquals( $project->id_customer, $user->email );

        $this->assertEquals( $user->getPersonalTeam()->id, $project->id_team );

        $test = new CurlTest( array(
                'headers' => $this->test_data->headers,
                'path'    => "/api/v2/teams/{$project->id_team}/projects/{$project->id}",
                'method'  => 'PUT',
                'params'  => array( 'id_assignee' => $other_user->uid )
        ) );

        $response = $test->getResponse();

        $project = Projects_ProjectDao::findById( $project->id );
        $this->assertEquals( $project->id_assignee, $other_user->uid );


        $test = new CurlTest( array(
                'headers' => $this->test_data->headers,
                'path'    => "/api/v2/teams/{$project->id_team}/projects/{$project->id}",
                'method'  => 'PUT',
                'params'  => array( 'id_assignee' => null )
        ) );

        $response = $test->getResponse();

        $project = Projects_ProjectDao::findById( $project->id );
        $this->assertNull( $project->id_assignee );

    }

    public function test_project_name_can_be_changed() {
        $this->prepareUserAndApiKey();

        $project_body = integrationCreateTestProject( array(
                'headers' => $this->test_data->headers
        ) );

        $project = Projects_ProjectDao::findById( $project_body->id_project );

        $test = new CurlTest( array(
                'headers' => $this->test_data->headers,
                'path'    => "/api/v2/teams/{$project->id_team}/projects/{$project->id}",
                'method'  => 'PUT',
                'params'  => array( 'name' => 'My new project name' )
        ) );

        $response = $test->getResponse();

        $json = json_decode( $response[ 'body' ] );

        $this->assertContains(
                Utils::friendly_slug( 'My new project name' ),
                $json->project->analyze_url
        );
        $this->assertEquals( 'My new project name', $json->project->name );

        $project = Projects_ProjectDao::findById( $project->id );
        $this->assertEquals( 'My new project name', $project->name );

    }

}