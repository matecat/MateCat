<?php

/**
 * @group regression
 * @covers Chunks_ChunkDao::getByIdAndPassword
 * User: dinies
 * Date: 22/06/16
 * Time: 12.47
 */
class GetByIdAndPasswordChunkTest extends AbstractTest
{
    private $test_data;
    /**
     * @var Chunks_ChunkDao
     */
    protected $chunk_Dao;
    /**
     * @var Database
     */
    protected $database_instance;
    /**
     * @var Projects_ProjectStruct
     */
    protected $project;


    function setup()
    {
        $this->database_instance = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->chunk_Dao = new Chunks_ChunkDao($this->database_instance);

        /**
         * environment initialization
         */
        $this->test_data = new StdClass();

        $this->prepareUserAndApiKey();
        $this->project = integrationCreateTestProject(array(
                'headers' => $this->test_data->headers,
                'files' => array(
                    test_file_path('xliff/file-with-hello-world.xliff')
                ),
                'params' => array(
                    'target_lang' => 'fr'
                )
            )
        );
    }

    private function prepareUserAndApiKey()
    {
        
        $this->test_data->user = Factory_User::create();
        $this->test_data->api_key = Factory_ApiKey::create(array(
            'uid' => $this->test_data->user->uid,
        ));

        $this->test_data->headers = array(
            "X-MATECAT-KEY: {$this->test_data->api_key->api_key}",
            "X-MATECAT-SECRET: {$this->test_data->api_key->api_secret}"
        );

    }

    /**
     * @group regression
     * @covers Chunks_ChunkDao::getByIdAndPassword
     */
    function test_getByIdAndPassword_with_success()
    {

        $query="SELECT j.* FROM jobs AS j JOIN projects AS p ON p.id = id_project WHERE p.id={$this->project->id_project}";
        $wrapped_job=$this->database_instance->query($query)->fetchAll(PDO::FETCH_ASSOC);
        $job= $wrapped_job['0'];

        $result=$this->chunk_Dao->getByIdAndPassword($job['id'],$job['password']);
        $this->assertTrue($result instanceof Chunks_ChunkStruct);
        $this->assertEquals($job['id'],$result['id']);
        $this->assertEquals($job['password'], $result['password']);

    }

    /**
     * @group regression
     * @covers Chunks_ChunkDao::getByIdAndPassword
     */
    function test_getByIdAndPassword_with_failure()
    {


        $query="SELECT j.* FROM jobs AS j JOIN projects AS p ON p.id = id_project WHERE p.id={$this->project->id_project}";
        $wrapped_job=$this->database_instance->query($query)->fetchAll(PDO::FETCH_ASSOC);
        $job= $wrapped_job['0'];

        $job['id']+=100;
        $this->setExpectedException('Exception');
        $this->chunk_Dao->getByIdAndPassword($job['id'],$job['password']);

    }
}