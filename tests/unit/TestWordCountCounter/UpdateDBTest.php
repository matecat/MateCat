<?php


use TestHelpers\AbstractTest;
use WordCount\CounterModel;
use WordCount\WordCountStruct;

/**
 * @group  regression
 * @covers CounterModel::updateDB
 * User: dinies
 * Date: 14/06/16
 * Time: 18.31
 */
class UpdateDBTest extends AbstractTest {
    /**
     * @var  Database
     */
    protected $database_instance;
    protected $sql_delete_job;
    protected $sql_delete_first_segment;
    protected $sql_delete_second_segment;
    protected $job_id;
    protected $job_password;
    protected $first_segment_id;
    protected $second_segment_id;
    /**
     * @var Jobs_JobDao
     */
    protected $job_Dao;
    protected $job_struct;
    /**
     * @var WordCountStruct
     */
    protected $word_count_struct;
    /**
     * @var CounterModel
     */
    protected $word_counter;
    /**
     * @var Redis
     */
    protected $flusher;
    protected $number_of_words_changed;
    protected $first_half_of_number;
    protected $second_half_of_number;


    public function setUp() {
        parent::setUp();
        $this->first_half_of_number    = "4";
        $this->second_half_of_number   = "11";
        $sum_of_numbers                = (int)$this->first_half_of_number + (int)$this->second_half_of_number;
        $this->number_of_words_changed = "{$sum_of_numbers}";
        $this->database_instance       = Database::obtain( INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE );

        /**
         * job initialization
         */

        $this->job_password = "7ec09d1cad61";
        $this->job_struct   = new Jobs_JobStruct(
                [
                        'id'                                  => null, //SET NULL FOR AUTOINCREMENT -> in this case is only stored in cache so i will chose a casual value
                        'id_project'                          => random_int( 200000, 40000000 ),
                        'password'                            => $this->job_password,
                        'job_first_segment'                   => "5659",
                        'job_last_segment'                    => "5660",
                        'source'                              => "de-DE",
                        'target'                              => "it-IT",
                        'tm_keys'                             => '[]',
                        'id_translator'                       => "",
                        'job_type'                            => null,
                        'total_time_to_edit'                  => "0",
                        'avg_post_editing_effort'             => "0",
                        'id_job_to_revise'                    => null,
                        'last_opened_segment'                 => "5659",
                        'id_tms'                              => "1",
                        'id_mt_engine'                        => "1",
                        'create_date'                         => "2016-06-13 10:15:30",
                        'last_update'                         => "2016-06-13 10:15:45",
                        'disabled'                            => "0",
                        'owner'                               => "bar@foo.net",
                        'status_owner'                        => "active",
                        'status'                              => "active",
                        'status_translator'                   => null,
                        'completed'                           => false,
                        'new_words'                           => "{$this->number_of_words_changed}",
                        'draft_words'                         => "0.00",
                        'translated_words'                    => "0.00",
                        'approved_words'                      => "0.00",
                        'rejected_words'                      => "0.00",
                        'subject'                             => "medical_pharmaceutical",
                        'payable_rates'                       => '{"NO_MATCH":100,"50%-74%":100,"75%-84%":60,"85%-94%":60,"95%-99%":60,"100%":30,"100%_PUBLIC":30,"REPETITIONS":30,"INTERNAL":60,"MT":85}',
                        'revision_stats_typing_min'           => "0",
                        'revision_stats_translations_min'     => "0",
                        'revision_stats_terminology_min'      => "0",
                        'revision_stats_language_quality_min' => "0",
                        'revision_stats_style_min'            => "0",
                        'revision_stats_typing_maj'           => "0",
                        'revision_stats_translations_maj'     => "0",
                        'revision_stats_terminology_maj'      => "0",
                        'revision_stats_language_quality_maj' => "0",
                        'revision_stats_style_maj'            => "0",
                        'total_raw_wc'                        => "1",
                        'validator'                           => "xxxx"
                ]
        );

        $this->job_Dao        = new Jobs_JobDao( $this->database_instance );
        $jobStruct            = $this->job_Dao->createFromStruct( $this->job_struct );
        $this->job_id         = $jobStruct->id;
        $this->sql_delete_job = "DELETE FROM " . INIT::$DB_DATABASE . ".`jobs` WHERE id='" . $this->job_id . "';";


        /**
         * Segment initialization
         */
        $sql_insert_first_segment = "INSERT INTO " . INIT::$DB_DATABASE . ".`segments` 
    ( internal_id, id_file, segment, segment_hash, raw_word_count, xliff_mrk_id, xliff_ext_prec_tags, xliff_ext_succ_tags, show_in_cattool,xliff_mrk_ext_prec_tags,xliff_mrk_ext_succ_tags) values
    ( '21922356366' , " . $this->job_id . ", '- Auf der Fußhaut natürlich vorhandene Hornhautbakterien zersetzen sich durch auftretenden Schweiß an Ihren Füßen.' , 'e0170a2e381f1969056a7eb5e5bd0ac9', '" . $this->number_of_words_changed . "' , null, '', '' , '1' , null , null )";

        $this->database_instance->getConnection()->query( $sql_insert_first_segment );
        $this->first_segment_id = $this->database_instance->last_insert();


        $this->sql_delete_first_segment = "DELETE FROM " . INIT::$DB_DATABASE . ".`segments` WHERE id='" . $this->first_segment_id . "';";


        $this->word_count_struct = new WordCountStruct();
        $this->word_count_struct->setIdJob( $this->job_id );
        $this->word_count_struct->setJobPassword( $this->job_password );
        $this->word_count_struct->setNewWords( $this->number_of_words_changed );
        $this->word_count_struct->setDraftWords( 0 );
        $this->word_count_struct->setTranslatedWords( 0 );
        $this->word_count_struct->setApprovedWords( 0 );
        $this->word_count_struct->setRejectedWords( 0 );
        $this->word_count_struct->setIdSegment( $this->first_segment_id );
        $this->word_count_struct->setOldStatus( "NEW" );
        $this->word_count_struct->setNewStatus( "TRANSLATED" );


        $this->word_counter = new CounterModel( $this->word_count_struct );

        $this->flusher = new Predis\Client( INIT::$REDIS_SERVERS );
        $this->flusher->select( INIT::$INSTANCE_ID );
        $this->flusher->flushdb();

    }

    public function tearDown() {
        $this->database_instance->getConnection()->query( $this->sql_delete_job );
        $this->database_instance->getConnection()->query( $this->sql_delete_first_segment );
        $this->flusher->select( INIT::$INSTANCE_ID );
        $this->flusher->flushDB();
        parent::tearDown();
    }

    /**
     * @group  regression
     * @covers CounterModel::updateDB
     */
    public function test_updateDB_from_NEW_to_TRANSLATED() {


        /**
         * Check in database before update
         */
        $job_struct_param           = new Jobs_JobStruct( [] );
        $job_struct_param->id       = $this->job_id;
        $job_struct_param->password = $this->job_password;


        $result_wrapped = $this->job_Dao->read( $job_struct_param );
        $result_job     = $result_wrapped[ '0' ];

        $this->assertEquals( $this->number_of_words_changed, $result_job->new_words );
        $this->assertEquals( "0.00", $result_job->draft_words );
        $this->assertEquals( "0.00", $result_job->translated_words );
        $this->assertEquals( "0.00", $result_job->approved_words );
        $this->assertEquals( "0.00", $result_job->rejected_words );


        $this->word_counter->setOldStatus( "NEW" );
        $this->word_counter->setNewStatus( "TRANSLATED" );


        $first_word_struct_param = new WordCountStruct();
        $first_word_struct_param->setIdJob( $this->job_id );
        $first_word_struct_param->setJobPassword( $this->job_password );
        $first_word_struct_param->setIdSegment( $this->first_segment_id );
        $first_word_struct_param->setNewWords( "-" . $this->first_half_of_number );
        $first_word_struct_param->setDraftWords( 0 );
        $first_word_struct_param->setTranslatedWords( "+" . $this->first_half_of_number );
        $first_word_struct_param->setApprovedWords( 0 );
        $first_word_struct_param->setRejectedWords( 0 );
        $first_word_struct_param->setOldStatus( "NEW" );
        $first_word_struct_param->setNewStatus( "TRANSLATED" );


        $second_word_struct_param = new WordCountStruct();
        $second_word_struct_param->setIdJob( $this->job_id );
        $second_word_struct_param->setJobPassword( $this->job_password );
        $second_word_struct_param->setIdSegment( $this->first_segment_id );
        $second_word_struct_param->setNewWords( "-" . $this->second_half_of_number );
        $second_word_struct_param->setDraftWords( 0 );
        $second_word_struct_param->setTranslatedWords( "+" . $this->second_half_of_number );
        $second_word_struct_param->setApprovedWords( 0 );
        $second_word_struct_param->setRejectedWords( 0 );
        $second_word_struct_param->setOldStatus( "NEW" );
        $second_word_struct_param->setNewStatus( "TRANSLATED" );

        $struct_wrapper = [
                '0' => $first_word_struct_param,
                '1' => $second_word_struct_param
        ];

        $result_word_counter_struct = $this->word_counter->updateDB( $struct_wrapper );


        $this->assertTrue( $result_word_counter_struct instanceof WordCountStruct );
        $this->assertEquals( $this->job_id, $result_word_counter_struct->getIdJob() );
        $this->assertEquals( $this->job_password, $result_word_counter_struct->getJobPassword() );
        $this->assertEquals( $this->first_segment_id, $result_word_counter_struct->getIdSegment() );
        $this->assertEquals( 0, $result_word_counter_struct->getNewWords() );
        $this->assertEquals( 0, $result_word_counter_struct->getDraftWords() );
        $this->assertEquals( (int)$this->number_of_words_changed, $result_word_counter_struct->getTranslatedWords() );
        $this->assertEquals( 0, $result_word_counter_struct->getApprovedWords() );
        $this->assertEquals( 0, $result_word_counter_struct->getRejectedWords() );
        $this->assertEquals( "NEW", $result_word_counter_struct->getOldStatus() );
        $this->assertEquals( "TRANSLATED", $result_word_counter_struct->getNewStatus() );

        /**
         * Check in database after update
         */
        $this->flusher->flushdb();
        $result_wrapped = $this->job_Dao->read( $job_struct_param );
        $result_job     = $result_wrapped[ '0' ];
        $this->assertEquals( "0.00", $result_job->new_words );
        $this->assertEquals( "0.00", $result_job->draft_words );
        $this->assertEquals( $this->number_of_words_changed, $result_job->translated_words );
        $this->assertEquals( "0.00", $result_job->approved_words );
        $this->assertEquals( "0.00", $result_job->rejected_words );

    }
}