<?php

$root = realpath( dirname( __FILE__ ) . '/../../' );
include_once $root . "/inc/Bootstrap.php";
Bootstrap::start();
include_once INIT::$UTILS_ROOT . "/MyMemory.copyrighted.php";

use EditLog\EditLogSegmentStruct;
use Jobs\PeeJobStatsStruct;
use TaskRunner\Commons\AbstractDaemon;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 18/09/15
 * Time: 19.13
 */
class JobPEEAndTimeToEditRunner extends AbstractDaemon
{
    const NR_OF_JOBS = 100;
    const NR_OF_SEGS = 10000;

    private static $last_job_file_name = '';
    private static $max_job_to_process_file_name = '';

    private static $queryMaxJob = "
                SELECT min(id) AS min, max(id) AS max
                    FROM jobs
                    WHERE ( new_words + draft_words ) < 10
                    AND id > :id ";

    private static $queryFirst = "
                SELECT jobs.id, password, job_first_segment, job_last_segment, source, target, class_load
                    FROM jobs
                    JOIN engines ON id_mt_engine = engines.id
                    WHERE ( new_words + draft_words ) < 10
                    AND jobs.id >= :min_id AND jobs.id <= :max_id ";

    private static $querySegments = "
                SELECT suggestion,
                    translation,
                    raw_word_count,
                    time_to_edit, 
                    match_type
                    FROM segment_translations st
                    JOIN segments s ON st.id_segment = s.id
                    AND s.id BETWEEN :job_first_seg_id AND :job_last_seg_id
                    WHERE status IN( :translated_status, :approved_status )
                    AND id_job = :id_job
                    AND show_in_cattool = 1
                    AND id_segment >= :actual_seg_id
                    LIMIT ";

    public function __construct() {
        parent::__construct();
        self::$last_job_file_name           = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . '.lastjobprocessed_jpeer';
        self::$max_job_to_process_file_name = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . '.maxJobToProcess_jpeer';
        self::$sleepTime          = 60 * 60 * 24 * 30 * 1;
        Log::$fileName            = "evaluatePEE.log";
    }

    function main( array $args = null ) {

        $this->truncateOldRecords();

        $db               = Database::obtain();

        $lastProcessedJob = (int)file_get_contents( self::$last_job_file_name );
        $maxJobToProcess  = @file_get_contents( self::$max_job_to_process_file_name );

        do {

            $stmt = $db->getConnection()->prepare( self::$queryMaxJob );
            $stmt->setFetchMode( PDO::FETCH_ASSOC );
            $stmt->execute([
                    'id' => (int)$lastProcessedJob
            ]);
            $minJobMaxJob = $stmt->fetch();

            $minJob       = (int)$minJobMaxJob[ 'min' ];
            Log::doJsonLog( "Parsing Jobs starting from $minJob." );
            echo "Parsing Jobs starting from $minJob.\n";

            if( $maxJobToProcess !== false && $minJob < (int)$maxJobToProcess ){ // exclude old files if they exists
                $maxJob       = (int)$maxJobToProcess;
                Log::doJsonLog( "Parsing Jobs until $maxJob reached." );
                echo "Parsing Jobs until $maxJob reached.\n";
                //review configs
                sleep( 5 );
            } else {
                $maxJob       = (int)$minJobMaxJob[ 'max' ];
            }

            $start = time();

            //get a chunk of self::NR_OF_JOBS each time.
            for ( $firstJob = $minJob; $this->RUNNING && ( $firstJob <= $maxJob ); $firstJob += self::NR_OF_JOBS ) {

                $stmt = $db->getConnection()->prepare( self::$queryFirst );
                $stmt->setFetchMode( PDO::FETCH_ASSOC );
                $stmt->execute( [
                        'min_id' => $firstJob,
                        'max_id' => ( $firstJob + self::NR_OF_JOBS )
                ] );
                $jobs = $stmt->fetchAll();

                //iterate over completed jobs, evaluate incremental PEE and save it in the job row
                //Incremental PEE = sum( segment_pee * segment_raw_wordcount)
                //It will be normalized when necessary
                for ( $j = 0; $this->RUNNING && ( $j < count( $jobs ) ); $j++ ) {
                    $job = $jobs[ $j ];

                    $_jid                  = $job[ 'id' ];
                    $_password             = $job[ 'password' ];
                    $_job_first_segment    = $job[ 'job_first_segment' ];
                    $_job_last_segment     = $job[ 'job_last_segment' ];
                    $_source               = $job[ 'source' ];
                    $_target               = $job[ 'target' ];
                    $_mt_engine_class_name = $job[ 'class_load' ];

                    Log::doJsonLog( "job $_jid -> " . ( $_job_last_segment - $_job_first_segment ) . " segments" );
                    echo "job $_jid -> " . ( $_job_last_segment - $_job_first_segment ) . " segments\n";

                    $job_stats = [
                            'ALL' => [
                                    'raw_post_editing_effort' => 0,
                                    'raw_wc_job'              => 0,
                                    'time_to_edit_job'        => 0
                            ]
                    ];

                    $payable_rates_keys = array_keys( Analysis_PayableRates::$DEFAULT_PAYABLE_RATES );
                    foreach ( $payable_rates_keys as $fuzzy_band ) {

                        if ( strpos( $fuzzy_band, 'MT' ) !== false ) {
                            $job_stats [ $fuzzy_band . "_" . $_mt_engine_class_name ] = [
                                    'raw_post_editing_effort' => 0,
                                    'raw_wc_job'              => 0,
                                    'time_to_edit_job'        => 0
                            ];
                        }

                        $job_stats [ $fuzzy_band ] = [
                                'raw_post_editing_effort' => 0,
                                'raw_wc_job'              => 0,
                                'time_to_edit_job'        => 0
                        ];

                    }

                    //get a chunk of self::NR_OF_SEGS segments each time.
                    for ( $firstSeg = $_job_first_segment; $firstSeg <= $_job_last_segment; $firstSeg += self::NR_OF_SEGS ) {
                        if ( $firstSeg > $_job_last_segment ) {
                            $firstSeg = $_job_last_segment;
                        }
                        Log::doJsonLog( "starting from segment $firstSeg" );
                        echo "starting from segment $firstSeg\n";

                        $stmt = $db->getConnection()->prepare( self::$querySegments . self::NR_OF_SEGS );
                        $stmt->setFetchMode( PDO::FETCH_ASSOC );
                        $stmt->execute( [
                                'job_first_seg_id'  => $_job_first_segment,
                                'job_last_seg_id'   => $_job_last_segment,
                                'translated_status' => Constants_TranslationStatus::STATUS_TRANSLATED,
                                'approved_status'   => Constants_TranslationStatus::STATUS_APPROVED,
                                'id_job'            => $_jid,
                                'actual_seg_id'     => $firstSeg
                        ] );
                        $segments = $stmt->fetchAll();


                        //iterate over segments.
                        foreach ( $segments as $i => $segment ) {
                            $segment = new EditLogSegmentStruct( $segment );
                            
                            if ( $segment->isValidForPeeTable() ) {

                                $segment->job_target = $_target;

                                $_PEE = $segment->getPEE();

                                $job_stats[ 'ALL' ][ 'raw_wc_job' ] += $segment->raw_word_count;
                                $job_stats[ 'ALL' ][ 'time_to_edit_job' ] += $segment->time_to_edit;
                                $job_stats[ 'ALL' ][ 'raw_post_editing_effort' ] += $_PEE * $segment->raw_word_count;

                                if( strpos( $segment->match_type, 'MT' ) !== false ){
                                    $_match_type = $segment->match_type . "_" . $_mt_engine_class_name;
                                    $job_stats[ $_match_type ][ 'raw_wc_job' ] += $segment->raw_word_count;
                                    $job_stats[ $_match_type ][ 'time_to_edit_job' ] += $segment->time_to_edit;
                                    $job_stats[ $_match_type ][ 'raw_post_editing_effort' ] += $_PEE * $segment->raw_word_count;
                                }

                                $job_stats[ $segment->match_type ][ 'raw_wc_job' ] += $segment->raw_word_count;
                                $job_stats[ $segment->match_type ][ 'time_to_edit_job' ] += $segment->time_to_edit;
                                $job_stats[ $segment->match_type ][ 'raw_post_editing_effort' ] += $_PEE * $segment->raw_word_count;

                            }
                        }

                        //sleep 100 nanosecs
                        usleep( 100 );
                    }

                    //BEGIN TRANSACTION
                    $db->begin();

                    $jobsStatsDao = new Jobs_JobStatsDao($db);
                    foreach ( $job_stats as $fuzzy_band => $job_stats_data ) {
                        $job_incremental_pee = $job_stats[ $fuzzy_band ][ 'raw_post_editing_effort' ];
                        $time_to_edit_job    = $job_stats[ $fuzzy_band ][ 'time_to_edit_job' ];
                        $raw_wc_job          = $job_stats[ $fuzzy_band ][ 'raw_wc_job' ];

                        Log::doJsonLog( "job pee[".$fuzzy_band."]: $job_incremental_pee\njob time to edit: $time_to_edit_job\njob total wc:$raw_wc_job\nWriting into DB" );
                        echo "job pee[".$fuzzy_band."]: $job_incremental_pee\njob time to edit: $time_to_edit_job\njob total wc:$raw_wc_job\nWriting into DB\n";

                        $jobStatsObj                          = new PeeJobStatsStruct();
                        $jobStatsObj->id_job                  = $_jid;
                        $jobStatsObj->password                = $_password;
                        $jobStatsObj->fuzzy_band              = $fuzzy_band;
                        $jobStatsObj->source                  = $_source;
                        $jobStatsObj->target                  = $_target;
                        $jobStatsObj->avg_post_editing_effort = $job_incremental_pee;
                        $jobStatsObj->total_time_to_edit      = $time_to_edit_job;
                        $jobStatsObj->total_raw_wc            = $raw_wc_job;

                        $jobsStatsDao->create( $jobStatsObj );

                    }

                    Log::doJsonLog( "done" );
                    echo "done.\n";

                    if ( !file_put_contents( self::$last_job_file_name, $_jid ) ) {
                        $db->rollback();
                        Utils::sendErrMailReport(
                            "",
                            "[JobPostEditingEffortRunner] Failed to process job $_jid"
                        );
                        $this->RUNNING = false;

                        continue;
                        //exit;
                    }

                    //COMMIT TRANSACTION
                    $db->commit();

                }
            }

            Log::doJsonLog( "took " . ( time() - $start ) / 60 . " minutes" );
            echo "took " . ( time() - $start ) / 60 . " minutes\n";

            Log::doJsonLog( "Everything completed. I can die." );
            echo "Everything completed. I can die.\n";

            //for the moment, this daemon is single-loop-execution
            $this->RUNNING = false;

            if ( $this->RUNNING ) {
                sleep( self::$sleepTime );
            }

        }
        while ( $this->RUNNING );
    }

    public static function cleanShutDown() {
        // TODO: Implement cleanShutDown() method.
    }

    /**
     * Every cycle reload and update Daemon configuration.
     * @return void
     */
    protected function _updateConfiguration() {
        // TODO: Implement _updateConfiguration() method.
    }

    protected function truncateOldRecords(){

        //Clean old Table Stat
        $dropCredentials = @parse_ini_file( realpath( dirname( __FILE__ ) ) . '/.passwd.ini' );

        $dropConnection = new PDO(
                "mysql:host=" . INIT::$DB_SERVER . ";dbname=" . INIT::$DB_DATABASE,
                $dropCredentials[ 'DB_USER' ],
                $dropCredentials[ 'DB_PASS' ],
                array(
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Raise exceptions on errors
                        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                ) );

        $dropConnection->exec( "SET names utf8" );
        $dropConnection->exec( "TRUNCATE TABLE jobs_stats" );

    }

}

$jpe = JobPEEAndTimeToEditRunner::getInstance();
/**
 * @var $jpe JobPEEAndTimeToEditRunner
 */
$jpe->main( null );
