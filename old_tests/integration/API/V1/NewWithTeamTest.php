<?php

class NewWithTeamTest extends IntegrationTest {

    protected $test_data;

    function setup() {
        $this->path   = '/api/new';
        $this->method = 'POST';

        $this->test_data = new stdClass();

        parent::setUp();
    }

    private function prepareUserAndKey() {
        $this->test_data->user    = Factory_User::create();
        $this->test_data->api_key = Factory_ApiKey::create( array(
                'uid' => $this->test_data->user->uid,
        ) );
    }

    function test_api_key_without_team_assigns_to_personal_team() {
        $this->prepareUserAndKey();

        $this->headers = array(
                "X-MATECAT-KEY: {$this->test_data->api_key->api_key}",
                "X-MATECAT-SECRET: {$this->test_data->api_key->api_secret}"
        );

        $this->params = array(
                'project_name' => 'foo',
                'target_lang'  => 'it-IT',
                'source_lang'  => 'en-US',
        );

        $this->files[] = test_file_path( 'xliff/amex-test.docx.xlf' );

        $response = $this->getResponse();
        $body     = json_decode( $response[ 'body' ] );

        // check
        //
        $project = Projects_ProjectDao::findById( $body->id_project );

        $this->assertEquals( $project->id_customer, $this->test_data->user->email );
        $this->assertEquals( $response[ 'code' ], 200 );

        $user = ( new Users_UserDao() )->getByEmail( $this->test_data->user->email );
        $this->assertEquals( $project->id_team, $user->getPersonalTeam()->id );
    }


}