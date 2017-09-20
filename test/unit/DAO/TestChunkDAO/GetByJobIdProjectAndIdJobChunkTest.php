<?php

/**
 * @group regression
 * @covers Chunks_ChunkDao::getByJobIdProjectAndIdJob
 * User: dinies
 * Date: 30/06/16
 * Time: 19.01
 */
class GetByIdProjectAndIdJobChunkTest extends AbstractTest
{
    /**
     * @var Chunks_ChunkDao
     */
    protected $chunk_Dao;
    /**
     * @var Projects_ProjectStruct
     */
    protected $project;
    /**
     * @var Jobs_JobStruct
     */
    protected $job;
    /**
     * @var Database
     */
    protected $database_instance;
    /**
     * @var UnitTestInitializer
     */
    protected $test_initializer;

    public function setUp(){
        parent::setUp();

        $this->database_instance = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );
        $this->chunk_Dao = new Chunks_ChunkDao($this->database_instance);

        $this->test_initializer= new UnitTestInitializer($this->database_instance);
        $this->project= $this->test_initializer->getProject();
        $this->job= $this->test_initializer->getJob();



    }

    /**
     * @group regression
     * @covers Chunks_ChunkDao::getByJobIdProjectAndIdJob
     */
    function test_getByJobId()
    {
        $expected_chunk= $this->test_initializer->getJob();
        $wrapped_result=$this->chunk_Dao->getByJobIdProjectAndIdJob($this->project['id'],$this->job['id']);
        $result= $wrapped_result['0'];
        $this->assertTrue($result instanceof Chunks_ChunkStruct);
        $this->assertEquals($expected_chunk['id'],$result['id']);
        $this->assertEquals($expected_chunk['password'], $result['password']);
        $this->assertEquals($expected_chunk['id_project'], $result['id_project']);
        $this->assertEquals($expected_chunk['job_first_segment'], $result['job_first_segment']);
        $this->assertEquals($expected_chunk['job_last_segment'], $result['job_last_segment']);
        $this->assertEquals($expected_chunk['source'], $result['source']);
        $this->assertEquals($expected_chunk['target'], $result['target']);
        $this->assertEquals($expected_chunk['tm_keys'], $result['tm_keys']);
        $this->assertEquals($expected_chunk['id_translator'], $result['id_translator']);
        $this->assertEquals($expected_chunk['job_type'],$result['job_type']);
        $this->assertEquals($expected_chunk['total_time_to_edit'], $result['total_time_to_edit']);
        $this->assertEquals($expected_chunk['avg_post_editing_effort'], $result['avg_post_editing_effort']);
        $this->assertEquals($expected_chunk['id_job_to_revise'],$result['id_job_to_revise']);
        $this->assertEquals($expected_chunk['last_opened_segment'], $result['last_opened_segment']);
        $this->assertEquals($expected_chunk['id_tms'], $result['id_tms']);
        $this->assertEquals($expected_chunk['id_mt_engine'], $result['id_mt_engine']);
        $this->assertEquals($expected_chunk['create_date'], $result['create_date']);
        $this->assertEquals($expected_chunk['last_update'], $result['last_update']);
        $this->assertEquals($expected_chunk['disabled'], $result['disabled']);
        $this->assertEquals($expected_chunk['owner'], $result['owner']);
        $this->assertEquals($expected_chunk['status_owner'], $result['status_owner']);
        $this->assertEquals($expected_chunk['status'], $result['status']);
        $this->assertEquals($expected_chunk['status_translator'],$result['status_translator']);
        $this->assertEquals($expected_chunk['completed'],$result['completed']);
        $this->assertEquals($expected_chunk['new_words'], $result['new_words']);
        $this->assertEquals($expected_chunk['draft_words'], $result['draft_words']);
        $this->assertEquals($expected_chunk['translated_words'], $result['translated_words']);
        $this->assertEquals($expected_chunk['approved_words'], $result['approved_words']);
        $this->assertEquals($expected_chunk['rejected_words'], $result['rejected_words']);
        $this->assertEquals($expected_chunk['subject'], $result['subject']);
        $this->assertEquals($expected_chunk['payable_rates'], $result['payable_rates']);
        $this->assertEquals($expected_chunk['revision_stats_typing_min'], $result['revision_stats_typing_min']);
        $this->assertEquals($expected_chunk['revision_stats_translations_min'], $result['revision_stats_translations_min']);
        $this->assertEquals($expected_chunk['revision_stats_terminology_min'], $result['revision_stats_terminology_min']);
        $this->assertEquals($expected_chunk['revision_stats_language_quality_min'], $result['revision_stats_language_quality_min']);
        $this->assertEquals($expected_chunk['revision_stats_style_min'], $result['revision_stats_style_min']);
        $this->assertEquals($expected_chunk['revision_stats_typing_maj'], $result['revision_stats_typing_maj']);
        $this->assertEquals($expected_chunk['revision_stats_translations_maj'], $result['revision_stats_translations_maj']);
        $this->assertEquals($expected_chunk['revision_stats_terminology_maj'], $result['revision_stats_terminology_maj']);
        $this->assertEquals($expected_chunk['revision_stats_language_quality_maj'], $result['revision_stats_language_quality_maj']);
        $this->assertEquals($expected_chunk['revision_stats_style_maj'], $result['revision_stats_style_maj']);
        $this->assertEquals($expected_chunk['total_raw_wc'], $result['total_raw_wc']);

    }
}