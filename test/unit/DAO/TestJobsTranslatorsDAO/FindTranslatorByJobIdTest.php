<?php
use ProjectQueue\Queue;
use Translators\JobsTranslatorsDao;
use Translators\JobsTranslatorsStruct;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 11/04/17
 * Time: 16.56
 *
 */
class FindTranslatorByJobIdTest extends AbstractTest {

    protected $database_instance;
    protected $job;

    /**
     * @var JobsTranslatorsDao
     */
    protected $jobTranslators;
    protected $project;

    protected $projectJson = '{"HTTP_HOST":"http:\/\/localhost","id_project":483,"create_date":"2017-04-11 19:34:33","id_customer":"translated_user","user_ip":"172.18.0.1","project_name":"WhiteHouse.doc.sdlxliff","result":{"errors":{},"data":{}},"private_tm_key":[],"private_tm_user":null,"private_tm_pass":null,"uploadToken":"{CC0A8AC5-D3DA-1377-F7A1-97BAF9A65ACE}","array_files":["WhiteHouse.doc.sdlxliff"],"file_id_list":{},"file_references":{},"source_language":"en-US","target_language":["it-IT"],"job_subject":"general","mt_engine":1,"tms_engine":1,"ppassword":"6576673e9385","array_jobs":{"job_list":{},"job_pass":{},"job_segments":{},"job_languages":{},"payable_rates":{}},"job_segments":{},"segments":{},"segments_metadata":{},"translations":{},"notes":{},"query_translations":{},"status":"NOT_READY_FOR_ANALYSIS","job_to_split":null,"job_to_split_pass":null,"split_result":null,"job_to_merge":null,"lang_detect_files":{"WhiteHouse.doc.sdlxliff":"detect"},"tm_keys":{},"userIsLogged":false,"uid":null,"skip_lang_validation":true,"pretranslate_100":0,"owner":"","word_count_type":"","metadata":{"lexiqa":false,"speech2text":false,"tag_projection":true,"segmentation_rule":""},"id_assignee":null,"session":{},"instance_id":0,"id_team":null,"team":null,"sanitize_project_options":true}';

    protected $result;

    public function setUp() {
        parent::setUp();
    }

    /**
     * @throws Exception
     */
    public function testCreationComplete() {

        $this->markTestSkipped( "Can not be executed without conflicting with main database schema and storage" );

        $this->project = new ArrayObject( json_decode( $this->projectJson, true ) );

        //reserve a project id from the sequence
        $this->project[ 'id_project' ] = Database::obtain()->nextSequence( Database::SEQ_ID_PROJECT )[ 0 ];
        $this->project[ 'ppassword' ]  = Utils::randomString();

        $hash = strtoupper( hash( 'ripemd128', uniqid( "", true ) . md5( uniqid( "", true ) ) ) );
        $guid = '{' . substr( $hash, 0, 8 ) . '-' . substr( $hash, 8, 4 ) . '-' . substr( $hash, 12, 4 ) . '-' . substr( $hash, 16, 4 ) . '-' . substr( $hash, 20, 12 ) . '}';

        $dirUpload = INIT::$UPLOAD_REPOSITORY . DIRECTORY_SEPARATOR . $guid;

        if ( !file_exists( $dirUpload ) ) {
            mkdir( $dirUpload, 0775 );
        }

        $this->project[ "uploadToken" ] = $guid;
        copy( test_file_path( 'xliff/file-with-hello-world.xliff' ), $dirUpload . DIRECTORY_SEPARATOR . 'file-with-hello-world.xliff' );
        $this->project[ "array_files" ] = [ "file-with-hello-world.xliff" ];
        $this->project[ "instance_id" ] = INIT::$INSTANCE_ID;

        FsFilesStorage::moveFileFromUploadSessionToQueuePath( $guid );
        $redisHandler = ( new RedisHandler() )->getConnection();
        $redisHandler->del( [ 'project_completed:' . $this->project[ 'id_project' ] ] );

        Queue::sendProject( $this->project );

        $time = time();
        do {
            $this->result = Queue::getPublishedResults( $this->project[ 'id_project' ] ); //LOOP for 290 seconds **** UGLY **** Deprecate in API V2
            if ( $this->result != null ) {
                break;
            }
            sleep( 2 );
        } while ( time() - $time <= 290 );

        if ( $this->result == null ) {
            throw new Exception( 'Execution timeout', 504 );

        } elseif ( !empty( $this->result[ 'errors' ] ) ) {
            //errors already logged
            print_r( $this->result[ 'errors' ] );
            throw new Exception( 'Project Creation Failure', 500 );

        } else {
            $this->assertEquals( $this->project[ 'id_project' ], $this->result[ 'id_project' ] );
            $this->assertEquals( $this->project[ 'ppassword' ], $this->result[ 'ppassword' ] );
            $this->assertTrue( stripos( $this->result[ 'analyze_url' ], 'localhost' ) !== false );
            $this->assertTrue( stripos( $this->result[ 'analyze_url' ], $this->project[ 'ppassword' ] ) !== false );
            //everything ok
        }

        return [ $this->project, $this->result ];

    }

    /**
     * @depends testCreationComplete
     *
     * @param $resultStruct
     *
     * @return array
     */
    public function testInsertTranslator( $resultStruct ) {

        $this->project = $resultStruct[ 0 ];
        $this->result  = $resultStruct[ 1 ];

        $jTranslatorsStruct = new JobsTranslatorsStruct();
        $jTranslatorsStruct->id_job = $this->result[ 'id_job' ][ 0 ];
        $jTranslatorsStruct->delivery_date = Utils::mysqlTimestamp( time() + 86400 );
        $jTranslatorsStruct->added_by = 1;
        $jTranslatorsStruct->email = 'domenico@translated.net';
        $jTranslatorsStruct->job_password = $this->result[ 'password' ][ 0 ];
        $jTranslatorsStruct->source = $this->result[ 'source_language' ];
        $jTranslatorsStruct->target = $this->result[ 'target_language' ][ 0 ];

        $jTranslatorsDao = new JobsTranslatorsDao();
        $jTranslatorsDao->insertStruct( $jTranslatorsStruct, [ 'no_nulls' => true ] );

        $resultStruct[] = $jTranslatorsStruct;
        return $resultStruct;

    }

    /**
     * @depends testInsertTranslator
     *
     * @param $resultStruct
     */
    public function testFindByJobId( $resultStruct ) {

        $this->project = $resultStruct[ 0 ];
        $this->result  = $resultStruct[ 1 ];

        $this->jobTranslators = new JobsTranslatorsDao( Database::obtain() );

        $result = $this->jobTranslators->findByJobId( $this->result[ 'id_job' ][ 0 ] )[ 0 ];
        $this->assertTrue( $result instanceof JobsTranslatorsStruct );


    }

    /**
     * @depends testInsertTranslator
     *
     * @param $resultStruct
     */
    public function testFindByJobIdAndPassword( $resultStruct ) {

        $this->project      = $resultStruct[ 0 ];
        $this->result       = $resultStruct[ 1 ];

        /**
         * @var $jTranslatorsStruct JobsTranslatorsStruct
         */
        $jTranslatorsStruct = $resultStruct[ 2 ];

        $this->jobTranslators = new JobsTranslatorsDao( Database::obtain() );

        $jobStruct = Jobs_JobDao::getByIdAndPassword( $jTranslatorsStruct->id_job, $jTranslatorsStruct->job_password );
        $this->assertTrue( $jobStruct instanceof Jobs_JobStruct );

        $result = $this->jobTranslators->findByJobsStruct( $jobStruct )[ 0 ];
        $this->assertTrue( $result instanceof JobsTranslatorsStruct );

    }

}