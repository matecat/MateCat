<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/04/16
 * Time: 20.13
 *
 */

use Contribution\ContributionSetStruct;
use Exceptions\ValidationError;
use TestHelpers\AbstractTest;


class ContributionStructTest extends AbstractTest {

    /**
     * @var ContributionSetStruct
     */
    protected $contributionStruct;

    protected $expected = [];

    public function setUp() {
        parent::setUp();

        $this->expected = new Jobs_JobStruct(

                [
                        'id'                      => '1886428338',
                        'password'                => 'a90acf203402',
                        'id_project'              => '22222222',
                        'job_first_segment'       => '167',
                        'job_last_segment'        => '177',
                        'source'                  => 'en-US',
                        'target'                  => 'it-IT',
                        'tm_keys'                 => '[{"tm":true,"glos":true,"owner":true,"uid_transl":null,"uid_rev":null,"name":"en - us_it . tmx","key":"7f6e65cde5907af8d75a","r":true,"w":true,"r_transl":null,"w_transl":null,"r_rev":null,"w_rev":null,"source":null,"target":null}]',
                        'id_translator'           => 'MyMemory_5cc4186a2fe329590980',
                        'job_type'                => null,
                        'total_time_to_edit'      => '6870210',
                        'avg_post_editing_effort' => '0',
                        'only_private_tm'         => '0',
                    //'id_job_to_revise'                    => null,
                        'last_opened_segment'     => '168',
                        'id_tms'                  => '1',
                        'id_mt_engine'            => '1',
                        'create_date'             => '2016-04-15 20:53:25',
                        'last_update'             => '2016-04-20 18:24:47',
                        'disabled'                => '0',
                        'owner'                   => 'domenico@translated.net',
                        'status_owner'            => 'active',
                        'status_translator'       => null,
                        'status'                  => 'active',
                        'completed'               => true,
                        'new_words'               => '94.80',
                        'draft_words'             => '0.00',
                        'translated_words'        => '10.50',
                        'approved_words'          => '0.00',
                        'rejected_words'          => '0.00',
                        'subject'                 => 'general',
                        'payable_rates'           => '{"NO_MATCH":100,"50 % -74 % ":100,"75 % -84 % ":60,"85 % -94 % ":60,"95 % -99 % ":60,"100 % ":30,"100 % _PUBLIC":30,"REPETITIONS":30,"INTERNAL":60,"MT":80}',
                        'total_raw_wc'            => '1',
                        'cached_results'          => []
                ]

        );

        $this->contributionStruct                       = new ContributionSetStruct();
        $this->contributionStruct->fromRevision         = true;
        $this->contributionStruct->id_job               = 1886428338;
        $this->contributionStruct->job_password         = "a90acf203402";
        $this->contributionStruct->segment              = '<g id="pt2">WASHINGTON </g><g id="pt3">— The Treasury Department and Internal Revenue Service today requested public comment on issues relating to the shared responsibility provisions included in the Affordable Care Act that will apply to certain employers starting in 2014.</g>';
        $this->contributionStruct->translation          = '<g id="pt2">WASHINGTON </g><g id="pt3">- Il Dipartimento del Tesoro e Agenzia delle Entrate oggi ha chiesto un commento pubblico su questioni relative alle disposizioni di responsabilità condivise incluse nel Affordable Care Act che si applicheranno a certi datori di lavoro a partire dal 2014.</g>';
        $this->contributionStruct->api_key              = INIT::$MYMEMORY_API_KEY;
        $this->contributionStruct->uid                  = 1234;
        $this->contributionStruct->oldTranslationStatus = 'NEW';
        $this->contributionStruct->oldSegment           = $this->contributionStruct->segment; //we do not change the segment source
        $this->contributionStruct->oldTranslation       = $this->contributionStruct->translation . " TEST";


    }

    public function tearDown() {
        $redisHandler = ( new RedisHandler() )->getConnection();
        $redisHandler->flushdb();
        parent::tearDown();
    }

    public function testContributionStruct() {

        $expected = [
                'fromRevision'         => true,
                'segment'              => '<g id="pt2">WASHINGTON </g><g id="pt3">— The Treasury Department and Internal Revenue Service today requested public comment on issues relating to the shared responsibility provisions included in the Affordable Care Act that will apply to certain employers starting in 2014.</g>',
                'translation'          => '<g id="pt2">WASHINGTON </g><g id="pt3">- Il Dipartimento del Tesoro e Agenzia delle Entrate oggi ha chiesto un commento pubblico su questioni relative alle disposizioni di responsabilità condivise incluse nel Affordable Care Act che si applicheranno a certi datori di lavoro a partire dal 2014.</g>',
                'id_job'               => 1886428338,
                'job_password'         => "a90acf203402",
                'oldSegment'           => '<g id="pt2">WASHINGTON </g><g id="pt3">— The Treasury Department and Internal Revenue Service today requested public comment on issues relating to the shared responsibility provisions included in the Affordable Care Act that will apply to certain employers starting in 2014.</g>',
                'oldTranslation'       => '<g id="pt2">WASHINGTON </g><g id="pt3">- Il Dipartimento del Tesoro e Agenzia delle Entrate oggi ha chiesto un commento pubblico su questioni relative alle disposizioni di responsabilità condivise incluse nel Affordable Care Act che si applicheranno a certi datori di lavoro a partire dal 2014.</g> TEST',
                'api_key'              => INIT::$MYMEMORY_API_KEY,
                'uid'                  => 1234,
                'oldTranslationStatus' => 'NEW',
                'propagationRequest'   => true,
                'id_segment'           => null,
                'context_before'       => '',
                'context_after'        => '',
                'props'                => [],
                'id_mt'                => null,
                'id_file'              => null
        ];

        $this->assertEquals( $expected, $this->contributionStruct->toArray() );

    }

    public function testJobStructValue() {

        // clone a new object to get an object on which inject the cache
        $clonedContributionSetStruct = clone $this->contributionStruct;
        $reflectionClass             = new ReflectionClass( $clonedContributionSetStruct );
        $refProperty                 = $reflectionClass->getProperty( 'cached_results' );
        $refProperty->setAccessible( true );
        $refProperty->setValue( $clonedContributionSetStruct, [ '_contributionJob' => $this->expected ] );

        $this->assertNotEmpty( $clonedContributionSetStruct->getJobStruct() );
        $this->assertEquals( $this->expected, $clonedContributionSetStruct->getJobStruct() );

    }

    /**
     * @throws ReflectionException
     * @throws ValidationError
     */
    public function testJobStructCacheSystem() {

        $redisHandler = ( new RedisHandler() )->getConnection();
        $refMethod    = new ReflectionMethod( '\Jobs_JobDao', '_getStatementForCache' );
        $refMethod->setAccessible( true );
        $statement = $refMethod->invoke( new Jobs_JobDao( Database::obtain() ), [ null ] );

        //check that there is no cache
        $this->assertEmpty( unserialize(
                $redisHandler->get( md5( $statement->queryString . serialize(
                                [
                                        'id_job'   => (string)$this->expected->id, // convert to string for serialization
                                        'password' => $this->expected->password
                                ]
                        ) ) )
        ) );

        //fill the cache
        $this->contributionStruct->getJobStruct();

        //check the cached value
        $JobStruct = unserialize(
                $redisHandler->get( md5( $statement->queryString . serialize(
                                [
                                        'id_job'   => (string)$this->expected->id, // convert to string for serialization
                                        'password' => $this->expected->password
                                ]
                        ) ) )
        )[ 0 ];

        $this->assertEquals( $JobStruct, $this->contributionStruct->getJobStruct() );

    }

    /**
     * Work with fields pre-filled in the database
     * @return void
     * @throws ValidationError
     */
    public function testJobPropsCache() {

        //fill the cache
        $this->assertNotEmpty( $this->contributionStruct->getProp() );
        $this->assertEquals( $this->contributionStruct->getProp(), [
                'project_id'   => "1886428330",
                'project_name' => "testXLif.xlf",
                'job_id'       => "1886428338"
        ] );

    }

}