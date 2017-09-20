<?php

/**
 * @group regression
 * @covers Chunks_ChunkDao::getByJobId
 * User: dinies
 * Date: 30/06/16
 * Time: 18.17
 */
class GetByJobIdChunkTest extends AbstractTest
{
    /**
     * @var Chunks_ChunkDao
     */
    protected $chunk_Dao;
    /**
     * @var Jobs_JobStruct
     */
    protected $job;

    /**
     * @var Database
     */
    protected $database_instance;


    public function setUp(){
        parent::setUp();

        $this->database_instance = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->chunk_Dao = new Chunks_ChunkDao($this->database_instance);

        $test_initializer= new UnitTestInitializer($this->database_instance);
        $this->job= $test_initializer->getJob();


    }

    /**
     * @group regression
     * @covers Chunks_ChunkDao::getByJobId
     */
    function test_getByJobId()
    {

        $wrapped_result=$this->chunk_Dao->getByJobId($this->job['id']);
        $result= $wrapped_result['0'];
        $this->assertTrue($result instanceof Chunks_ChunkStruct);
        $this->assertEquals($this->job['id'],$result['id']);
        $this->assertEquals($this->job['password'], $result['password']);
        $this->assertEquals($this->job['id_project'], $result['id_project']);
        $this->assertEquals($this->job['job_first_segment'], $result['job_first_segment']);
        $this->assertEquals($this->job['job_last_segment'], $result['job_last_segment']);
        $this->assertEquals($this->job['source'], $result['source']);
        $this->assertEquals($this->job['target'], $result['target']);
        $this->assertEquals($this->job['tm_keys'], $result['tm_keys']);
        $this->assertEquals($this->job['id_translator'], $result['id_translator']);
        $this->assertEquals($this->job['job_type'],$result['job_type']);
        $this->assertEquals($this->job['total_time_to_edit'], $result['total_time_to_edit']);
        $this->assertEquals($this->job['avg_post_editing_effort'], $result['avg_post_editing_effort']);
        $this->assertEquals($this->job['id_job_to_revise'],$result['id_job_to_revise']);
        $this->assertEquals($this->job['last_opened_segment'], $result['last_opened_segment']);
        $this->assertEquals($this->job['id_tms'], $result['id_tms']);
        $this->assertEquals($this->job['id_mt_engine'], $result['id_mt_engine']);
        $this->assertEquals($this->job['create_date'], $result['create_date']);
        $this->assertEquals($this->job['last_update'], $result['last_update']);
        $this->assertEquals($this->job['disabled'], $result['disabled']);
        $this->assertEquals($this->job['owner'], $result['owner']);
        $this->assertEquals($this->job['status_owner'], $result['status_owner']);
        $this->assertEquals($this->job['status'], $result['status']);
        $this->assertEquals($this->job['status_translator'],$result['status_translator']);
        $this->assertEquals($this->job['completed'],$result['completed']);
        $this->assertEquals($this->job['new_words'], $result['new_words']);
        $this->assertEquals($this->job['draft_words'], $result['draft_words']);
        $this->assertEquals($this->job['translated_words'], $result['translated_words']);
        $this->assertEquals($this->job['approved_words'], $result['approved_words']);
        $this->assertEquals($this->job['rejected_words'], $result['rejected_words']);
        $this->assertEquals($this->job['subject'], $result['subject']);
        $this->assertEquals($this->job['payable_rates'], $result['payable_rates']);
        $this->assertEquals($this->job['revision_stats_typing_min'], $result['revision_stats_typing_min']);
        $this->assertEquals($this->job['revision_stats_translations_min'], $result['revision_stats_translations_min']);
        $this->assertEquals($this->job['revision_stats_terminology_min'], $result['revision_stats_terminology_min']);
        $this->assertEquals($this->job['revision_stats_language_quality_min'], $result['revision_stats_language_quality_min']);
        $this->assertEquals($this->job['revision_stats_style_min'], $result['revision_stats_style_min']);
        $this->assertEquals($this->job['revision_stats_typing_maj'], $result['revision_stats_typing_maj']);
        $this->assertEquals($this->job['revision_stats_translations_maj'], $result['revision_stats_translations_maj']);
        $this->assertEquals($this->job['revision_stats_terminology_maj'], $result['revision_stats_terminology_maj']);
        $this->assertEquals($this->job['revision_stats_language_quality_maj'], $result['revision_stats_language_quality_maj']);
        $this->assertEquals($this->job['revision_stats_style_maj'], $result['revision_stats_style_maj']);
        $this->assertEquals($this->job['total_raw_wc'], $result['total_raw_wc']);

    }

}