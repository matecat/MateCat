<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/04/16
 * Time: 20.13
 *
 */

use \Contribution\ContributionSetStruct;

class ContributionStructTest extends AbstractTest {

    /**
     * @var ContributionSetStruct
     */
    protected $contributionStruct;

    Protected $expected = [];

    public function setUp() {
        parent::setUp();
        $redisHandler = new \Predis\Client( \INIT::$REDIS_SERVERS );
        $redisHandler->flushdb();
        Database::obtain()->getConnection()->exec( "DELETE FROM jobs WHERE id = 1999997" );
        Database::obtain()->getConnection()->exec( "DELETE FROM projects WHERE id = 22222222" );


        $insert = "INSERT INTO `jobs` (
            `id`, 
            `password`, 
            `id_project`, 
            `job_first_segment`, 
            `job_last_segment`, 
            `id_translator`, 
            `tm_keys`, 
            `job_type`, 
            `source`, 
            `target`,
            `total_time_to_edit`, 
            `last_opened_segment`, 
            `id_tms`, 
            `id_mt_engine`, 
            `create_date`, 
            `last_update`, 
            `disabled`,
            `owner`, 
            `status_owner`, 
            `status_translator`, 
            `status`, 
            `completed`, 
            `new_words`, 
            `draft_words`, 
            `translated_words`, 
            `approved_words`, 
            `rejected_words`, 
            `subject`,
            `payable_rates`,
            `revision_stats_typing_min`, 
            `revision_stats_translations_min`, 
            `revision_stats_terminology_min`, 
            `revision_stats_language_quality_min`,
            `revision_stats_style_min`, 
            `revision_stats_typing_maj`, 
            `revision_stats_translations_maj`,
            `revision_stats_terminology_maj`, 
            `revision_stats_language_quality_maj`, 
            `revision_stats_style_maj`, 
            `avg_post_editing_effort`,
            `total_raw_wc`
        ) VALUES (
            '1999997', 
            '1d7903464318', 
            '22222222', 
            '167', 
            '177', 
            'MyMemory_5cc4186a2fe329590980', 
            '[{\"tm\":true,\"glos\":true,\"owner\":true,\"uid_transl\":null,\"uid_rev\":null,\"name\":\"en - us_it . tmx\",\"key\":\"7f6e65cde5907af8d75a\",\"r\":true,\"w\":true,\"r_transl\":null,\"w_transl\":null,\"r_rev\":null,\"w_rev\":null,\"source\":null,\"target\":null}]', 
            NULL, 
            'en-US', 
            'it-IT', 
            '6870210', 
            '168', 
            '1', 
            '1', 
            '2016-04-15 20:53:25', 
            '2016-04-20 18:24:47',
            '0', 
            'domenico@translated.net', 
            'active',
            NULL, 
            'active', 
            '1', 
            '94.80', 
            '0.00', 
            '10.50', 
            '0.00', 
            '0.00', 
            'general', 
            '{\"NO_MATCH\":100,\"50 % -74 % \":100,\"75 % -84 % \":60,\"85 % -94 % \":60,\"95 % -99 % \":60,\"100 % \":30,\"100 % _PUBLIC\":30,\"REPETITIONS\":30,\"INTERNAL\":60,\"MT\":80}', 
            '0', 
            '0', 
            '0', 
            '0', 
            '0', 
            '0', 
            '0', 
            '0', 
            '0', 
            '0', 
            '0', 
            '1'
        )";

        Database::obtain()->getConnection()->exec( $insert );
        Database::obtain()->getConnection()->exec( "INSERT INTO `projects` (`id`, `password`, `id_customer`, `name`, `create_date`, `id_engine_tm`, `id_engine_mt`, `status_analysis`, `fast_analysis_wc`, `tm_analysis_wc`, `standard_analysis_wc`, `remote_ip_address`, `pretranslate_100`, `id_qa_model`) VALUES ('22222222', 'b9e73b518ca2', 'domenico@translated.net', 'MATECAT_PROJ-201604150853', '2016-04-15 20:53:18', NULL, NULL, 'DONE', '353.00', '105.30', '105.30', '127.0.0.1', '0', NULL );" );

        $this->contributionStruct               = new ContributionSetStruct();
        $this->contributionStruct->fromRevision = true;
        $this->contributionStruct->id_job       = 1999997;
        $this->contributionStruct->job_password = "1d7903464318";

        // methods does not exists anymore
        // $this->contributionStruct->segment = \CatUtils::layer2ToLayer0( '<g id="pt2">WASHINGTON </g><g id="pt3">— The Treasury Department and Internal Revenue Service today requested public
        // comment on issues relating to the shared responsibility provisions included in the Affordable Care Act that will apply to certain employers starting in 2014.</g>' );
        // $this->contributionStruct->translation = \CatUtils::layer2ToLayer0( '<g id="pt2">WASHINGTON </g><g id="pt3">- Il Dipartimento del Tesoro e Agenzia delle Entrate oggi ha chiesto un
        // commento pubblico su questioni relative alle disposizioni di responsabilità condivise incluse nel Affordable Care Act che si applicheranno a certi datori di lavoro a partire dal 2014.</g>' );

        $this->contributionStruct->segment              = '<g id="pt2">WASHINGTON </g><g id="pt3">— The Treasury Department and Internal Revenue Service today requested public comment on issues relating to the shared responsibility provisions included in the Affordable Care Act that will apply to certain employers starting in 2014.</g>';
        $this->contributionStruct->translation          = '<g id="pt2">WASHINGTON </g><g id="pt3">- Il Dipartimento del Tesoro e Agenzia delle Entrate oggi ha chiesto un commento pubblico su questioni relative alle disposizioni di responsabilità condivise incluse nel Affordable Care Act che si applicheranno a certi datori di lavoro a partire dal 2014.</g>';
        $this->contributionStruct->api_key              = \INIT::$MYMEMORY_API_KEY;
        $this->contributionStruct->uid                  = 1234;
        $this->contributionStruct->oldTranslationStatus = 'NEW';
        $this->contributionStruct->oldSegment           = $this->contributionStruct->segment; //we do not change the segment source
        $this->contributionStruct->oldTranslation       = $this->contributionStruct->translation . " TEST";

        $this->expected[] = new \Jobs_JobStruct(

                [
                        'id'                                  => '1999997',
                        'password'                            => '1d7903464318',
                        'id_project'                          => '22222222',
                        'job_first_segment'                   => '167',
                        'job_last_segment'                    => '177',
                        'source'                              => 'en-US',
                        'target'                              => 'it-IT',
                        'tm_keys'                             => '[{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"en - us_it . tmx","key":"7f6e65cde5907af8d75a","r":true,"w":true,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]',
                        'id_translator'                       => 'MyMemory_5cc4186a2fe329590980',
                        'job_type'                            => null,
                        'total_time_to_edit'                  => '6870210',
                        'avg_post_editing_effort'             => '0',
                        'only_private_tm'                     => '0',
                    //'id_job_to_revise'                    => null,
                        'last_opened_segment'                 => '168',
                        'id_tms'                              => '1',
                        'id_mt_engine'                        => '1',
                        'create_date'                         => '2016-04-15 20:53:25',
                        'last_update'                         => '2016-04-20 18:24:47',
                        'disabled'                            => '0',
                        'owner'                               => 'domenico@translated.net',
                        'status_owner'                        => 'active',
                        'status_translator'                   => null,
                        'status'                              => 'active',
                        'completed'                           => '1',
                        'new_words'                           => '94.80',
                        'draft_words'                         => '0.00',
                        'translated_words'                    => '10.50',
                        'approved_words'                      => '0.00',
                        'rejected_words'                      => '0.00',
                        'subject'                             => 'general',
                        'payable_rates'                       => '{"NO_MATCH":100,"50 % -74 % ":100,"75 % -84 % ":60,"85 % -94 % ":60,"95 % -99 % ":60,"100 % ":30,"100 % _PUBLIC":30,"REPETITIONS":30,"INTERNAL":60,"MT":80}',
                        'revision_stats_typing_min'           => '0',
                        'revision_stats_translations_min'     => '0',
                        'revision_stats_terminology_min'      => '0',
                        'revision_stats_language_quality_min' => '0',
                        'revision_stats_style_min'            => '0',
                        'revision_stats_typing_maj'           => '0',
                        'revision_stats_translations_maj'     => '0',
                        'revision_stats_terminology_maj'      => '0',
                        'revision_stats_language_quality_maj' => '0',
                        'revision_stats_style_maj'            => '0',
                        'total_raw_wc'                        => '1',
                        'cached_results'                      => []
                ]

        );

    }

    public function tearDown() {
        $redisHandler = new \Predis\Client( \INIT::$REDIS_SERVERS );
        $redisHandler->flushdb();
        Database::obtain()->getConnection()->exec( "DELETE FROM jobs WHERE id = 1999997" );
        Database::obtain()->getConnection()->exec( "DELETE FROM projects WHERE id = 22222222" );
        $reflectionClass = new ReflectionClass( $this->contributionStruct );
        $refProperty     = $reflectionClass->getProperty( 'cached_results' );
        $refProperty->setAccessible( true );
        $refProperty->setValue( $this->contributionStruct, [] );
        parent::tearDown();
    }

    public function testContributionStruct() {

        $expected = [
                'fromRevision'         => true,
                'segment'              => '<g id="pt2">WASHINGTON </g><g id="pt3">— The Treasury Department and Internal Revenue Service today requested public comment on issues relating to the shared responsibility provisions included in the Affordable Care Act that will apply to certain employers starting in 2014.</g>',
                'translation'          => '<g id="pt2">WASHINGTON </g><g id="pt3">- Il Dipartimento del Tesoro e Agenzia delle Entrate oggi ha chiesto un commento pubblico su questioni relative alle disposizioni di responsabilità condivise incluse nel Affordable Care Act che si applicheranno a certi datori di lavoro a partire dal 2014.</g>',
                'id_job'               => 1999997,
                'job_password'         => "1d7903464318",
                'oldSegment'           => '<g id="pt2">WASHINGTON </g><g id="pt3">— The Treasury Department and Internal Revenue Service today requested public comment on issues relating to the shared responsibility provisions included in the Affordable Care Act that will apply to certain employers starting in 2014.</g>',
                'oldTranslation'       => '<g id="pt2">WASHINGTON </g><g id="pt3">- Il Dipartimento del Tesoro e Agenzia delle Entrate oggi ha chiesto un commento pubblico su questioni relative alle disposizioni di responsabilità condivise incluse nel Affordable Care Act che si applicheranno a certi datori di lavoro a partire dal 2014.</g> TEST',
                'api_key'              => \INIT::$MYMEMORY_API_KEY,
                'uid'                  => 1234,
                'oldTranslationStatus' => 'NEW',
                'propagationRequest'   => true,
                'id_segment'           => null,
                'context_before'       => '',
                'context_after'        => '',
                'props'                => [],
                'id_mt'                => null,
        ];

        $this->assertEquals( $expected, $this->contributionStruct->toArray() );

    }

    public function testJobStructValue() {
        $this->assertNotEmpty( $this->contributionStruct->getJobStruct() );
        $this->assertEquals( $this->expected[0], $this->contributionStruct->getJobStruct() );

    }

    public function testJobStructCacheSystem() {

        $redisHandler = new \Predis\Client( \INIT::$REDIS_SERVERS );

        $refMethod = new ReflectionMethod( '\Jobs_JobDao', '_getStatementForCache' );
        $refMethod->setAccessible( true );
        $statement = $refMethod->invoke( new \Jobs_JobDao( Database::obtain() ) );

        //check that there is no cache
        $this->assertEmpty( unserialize(
                $redisHandler->get( md5( $statement->queryString . serialize(
                                [
                                        'id_job'   => (int)$this->expected[ 0 ]->id,
                                        'password' => $this->expected[ 0 ]->password
                                ]
                        ) ) )
        ) );

        //fill the cache
        $this->contributionStruct->getJobStruct();

        //check the cached value
        $JobStruct = unserialize(
                $redisHandler->get( md5( $statement->queryString . serialize(
                                [
                                        'id_job'   => (int)$this->expected[ 0 ]->id,
                                        'password' => $this->expected[ 0 ]->password
                                ]
                        ) ) )
        );

        /**
         * @TODO Redis in test env seems to have some problems. Understand why it happens!
         */
        //$this->assertEquals( $JobStruct, $this->contributionStruct->getJobStruct() );

    }

    public function testJobPropsCache() {

        $redisHandler = new \Predis\Client( \INIT::$REDIS_SERVERS );

        //check that there is no cache
        $this->assertEmpty( unserialize( $redisHandler->get( "project_data_for_job_id:" . $this->contributionStruct->id_job ) ) );

        //fill the cache
        $this->assertNotEmpty( $this->contributionStruct->getProp() );

        /**
         * @TODO Redis in test env seems to have some problems. Understand why it happens!
         */
        //now check the cache
        //$this->assertEquals( unserialize( $redisHandler->get( "project_data_for_job_id:" . $this->contributionStruct->id_job ) ), $this->contributionStruct->getProp() );

        $this->assertEquals( $this->contributionStruct->getProp(), [
                'project_id'   => "22222222",
                'project_name' => "MATECAT_PROJ-201604150853",
                'job_id'       => "1999997"
        ] );

    }

}
