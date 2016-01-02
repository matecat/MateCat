<?php

$root = realpath( dirname( __FILE__ ) . '/../../' );
include_once $root . "/inc/Bootstrap.php";
Bootstrap::start();
require_once INIT::$MODEL_ROOT . '/queries.php';
include_once INIT::$UTILS_ROOT . "/MyMemory.copyrighted.php";

use TaskRunner\Commons\AbstractDaemon;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 18/09/15
 * Time: 19.13
 */
class JobPEEAndTimeToEditRunner extends AbstractDaemon {
    const NR_OF_JOBS = 1000;
    const NR_OF_SEGS = 10000;

    private static $last_job_file_name = '';

    public function __construct() {
        parent::__construct();
        self::$last_job_file_name = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . '.lastjobprocessed_jpeer';
        self::$sleepTime          = 60 * 60 * 24 * 30 * 1;
        Log::$fileName            = "evaluatePEE.log";
    }

    function main( $args = null ) {
        $db               = Database::obtain();
        $lastProcessedJob = (int)file_get_contents( self::$last_job_file_name );

        do {
            $queryMaxJob = "select min(id) as min, max(id) as max
                            from jobs
                            where completed = 1
                            and id > %d";

            $queryFirst = "select id, password, job_first_segment, job_last_segment
                            from jobs
                            where completed = 1
                            and id >= %d and id <= %d";

            $querySegments = "select suggestion,
                         translation,
                         raw_word_count,
                         time_to_edit
                         from segment_translations st
                         join segments s on st.id_segment = s.id
                            and s.id between %d and %d
                         where status='translated'
                         and id_job = %d
                         and show_in_cattool = 1
                         and id_segment >= %d
                         limit %d";

            $queryUpdateJob = "update jobs
                                set avg_post_editing_effort = %f,
                                total_time_to_edit = %f,
                                total_raw_wc = %d
                                where id = %d and password = '%s'";

            $minJobMaxJob = $db->query_first(
                    sprintf(
                            $queryMaxJob,
                            (int)$lastProcessedJob
                    )
            );
            $maxJob       = (int)$minJobMaxJob[ 'max' ];
            $minJob       = (int)$minJobMaxJob[ 'min' ];

            $start = time();

            //get a chunk of self::NR_OF_JOBS each time.
            for ( $firstJob = $minJob; $this->RUNNING && ( $firstJob < $maxJob ); $firstJob += self::NR_OF_JOBS ) {

                $jobs = $db->fetch_array(
                        sprintf(
                                $queryFirst,
                                $firstJob,
                                ( $firstJob + self::NR_OF_JOBS )
                        )
                );

                //iterate over completed jobs, evaluate incremental PEE and save it in the job row
                //Incremental PEE = sum( segment_pee * segment_raw_wordcount)
                //It will be normalized when necessary
                for ( $j = 0; $this->RUNNING && ( $j < count( $jobs ) ); $j++ ) {
                    $job = $jobs[ $j ];

                    //BEGIN TRANSACTION
                    $db->begin();

                    $_jid               = $job[ 'id' ];
                    $_password          = $job[ 'password' ];
                    $_job_first_segment = $job[ 'job_first_segment' ];
                    $_job_last_segment  = $job[ 'job_last_segment' ];

                    Log::doLog( "job $_jid -> " . ( $_job_last_segment - $_job_first_segment ) . " segments" );
                    echo "job $_jid -> " . ( $_job_last_segment - $_job_first_segment ) . " segments\n";

                    $raw_post_editing_effort_job = 0;
                    $raw_wc_job                  = 0;
                    $time_to_edit_job            = 0;

                    //get a chunk of self::NR_OF_SEGS segments each time.
                    for ( $firstSeg = $_job_first_segment; $firstSeg <= $_job_last_segment; $firstSeg += self::NR_OF_SEGS ) {
                        if ( $firstSeg > $_job_last_segment ) {
                            $firstSeg = $_job_last_segment;
                        }
                        Log::doLog( "starting from segment $firstSeg" );
                        echo "starting from segment $firstSeg\n";

                        $segments = $db->fetch_array(
                                sprintf(
                                        $querySegments,
                                        $_job_first_segment,
                                        $_job_last_segment,
                                        $_jid,
                                        $firstSeg,
                                        self::NR_OF_SEGS
                                )
                        );

                        //iterate over segments.
                        foreach ( $segments as $i => $segment ) {
                            $segment = new EditLog_EditLogSegmentStruct( $segment );

                            $raw_wc_job += $segment->raw_word_count;
                            $time_to_edit_job += $segment->time_to_edit;
                            if ( $segment->isValidForEditLog() ) {
                                $raw_post_editing_effort_job += $segment->getPEE() * $segment->raw_word_count;
                            }
                        }

                        //sleep 100 nanosecs
                        usleep( 100 );
                    }

                    $job_incremental_pee = $raw_post_editing_effort_job;

                    Log::doLog( "job pee: $job_incremental_pee\njob time to edit: $time_to_edit_job\njob total wc:$raw_wc_job\nWriting into DB" );
                    echo "job pee: $job_incremental_pee\njob time to edit: $time_to_edit_job\njob total wc:$raw_wc_job\nWriting into DB\n";
                    $db->query(
                            sprintf(
                                    $queryUpdateJob,
                                    $job_incremental_pee,
                                    $time_to_edit_job,
                                    $raw_wc_job,
                                    $_jid,
                                    $_password
                            )
                    );

                    Log::doLog( "done" );
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

            Log::doLog( "took " . ( time() - $start ) / 60 . " seconds" );
            echo "took " . ( time() - $start ) / 60 . " seconds\n";

            Log::doLog( "sleeping for 1 month" );
            echo "sleeping for 1 month\n";

            if ( $this->RUNNING ) {
                sleep( self::$sleeptime );
            }

        } while ( $this->RUNNING );
    }

    public static function cleanShutDown() {
        // TODO: Implement cleanShutDown() method.
    }

}

$jpe = JobPEEAndTimeToEditRunner::getInstance();
/**
 * @var $jpe JobPEEAndTimeToEditRunner
 */
$jpe->main( null );
usleep( 1 );