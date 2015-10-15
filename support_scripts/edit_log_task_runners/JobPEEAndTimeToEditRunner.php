<?php

$root = realpath(dirname(__FILE__) . '/../../');
include_once $root . "/inc/Bootstrap.php";
Bootstrap::start();
require_once INIT::$MODEL_ROOT . '/queries.php';
include_once INIT::$UTILS_ROOT . "/MyMemory.copyrighted.php";

$db = Database::obtain(INIT::$DB_SERVER, INIT::$DB_USER, INIT::$DB_PASS, INIT::$DB_DATABASE);
$db->debug = false;
$db->connect();

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 18/09/15
 * Time: 19.13
 */
class JobPEEAndTimeToEditRunner extends Analysis_Abstract_AbstractDaemon
{
    const NR_OF_JOBS = 1000;
    const NR_OF_SEGS = 10000;

    private static $last_job_file_name = '';

    public function __construct()
    {
        parent::__construct();
        self::$last_job_file_name =  dirname( __FILE__ ) . DIRECTORY_SEPARATOR. '.lastjobprocessed_jpeer';
        self::$sleeptime = 60 * 60 * 24 * 30 * 1;
        Log::$fileName = "evaluatePEE.log";
    }

    function main($args)
    {
        $db = Database::obtain();
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
                         and id_segment > %d
                         limit %d";

            $queryUpdateJob = "update jobs
                                set avg_post_editing_effort = %f,
                                total_time_to_edit = %f
                                where id = %d and password = '%s'";

            $minJobMaxJob = $db->query_first(
                sprintf(
                    $queryMaxJob,
                    (int)$lastProcessedJob
                )
            );
            $maxJob = (int)$minJobMaxJob['max'];
            $minJob = (int)$minJobMaxJob['min'];

            $start = time();

            for ($firstJob = $minJob; self::$RUNNING && ($firstJob < $maxJob) ; $firstJob += self::NR_OF_JOBS) {

                $jobs = $db->fetch_array(
                    sprintf(
                        $queryFirst,
                        $firstJob,
                        ($firstJob + self::NR_OF_JOBS)
                    )
                );

                //iterate over completed jobs, evaluate PEE and save it in the job row
                for ($j = 0; self::$RUNNING && ( $j < count($jobs) ); $j++) {
                    $job = $jobs[$j];

                    //BEGIN TRANSACTION
                    $db->begin();

                    $_jid = $job['id'];
                    $_password = $job['password'];
                    $_job_first_segment = $job['job_first_segment'];
                    $_job_last_segment = $job['job_last_segment'];

                    Log::doLog("job $_jid -> " . ($_job_last_segment - $_job_first_segment) . " segments");
                    echo "job $_jid -> " . ($_job_last_segment - $_job_first_segment) . " segments\n";

                    $raw_post_editing_effort_job = 0;
                    $raw_wc_job = 0;
                    $time_to_edit_job = 0;

                    for ($firstSeg = $_job_first_segment; $firstSeg <= $_job_last_segment; $firstSeg += self::NR_OF_SEGS) {
                        if ($firstSeg > $_job_last_segment) {
                            $firstSeg = $_job_last_segment;
                        }
                        Log::doLog("starting from segment $firstSeg");
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

                        foreach ($segments as $i => $segment) {
                            $post_editing_effort = round(
                                (1 - MyMemory::TMS_MATCH($segment['suggestion'], $segment['translation'])) * 100
                            );

                            if ($post_editing_effort < 0) {
                                $post_editing_effort = 0;
                            } else if ($post_editing_effort > 100) {
                                $post_editing_effort = 100;
                            }

                            $raw_wc_job += $segment['raw_word_count'];
                            $time_to_edit_job += $segment['time_to_edit'];

                            $raw_post_editing_effort_job += $post_editing_effort * $segment['raw_word_count'];
                        }

                        //sleep 100 nanosecs
                        usleep(100);
                    }

                    $job_pee = round($raw_post_editing_effort_job / $raw_wc_job, 3);

                    Log::doLog("job pee: $job_pee\njob time to edit: $time_to_edit_job\nWriting into DB");
                    echo "job pee: $job_pee\njob time to edit: $time_to_edit_job\nWriting into DB\n";
                    $db->query(
                        sprintf(
                            $queryUpdateJob,
                            $job_pee,
                            $time_to_edit_job,
                            $_jid,
                            $_password
                        )
                    );

                    Log::doLog("done");
                    echo "done.\n";

                    if( ! file_put_contents(self::$last_job_file_name, $_jid) ){
                        $db -> rollback();
                        Utils::sendErrMailReport(
                            "",
                            "[JobPostEditingEffortRunner] Failed to process job $_jid"
                        );
                        self::$RUNNING = false;

                        continue;
                        //exit;
                    }
                    //COMMIT TRANSACTION
                    $db->commit();
                }
            }

            Log::doLog("took " . (time() - $start) / 60 . " seconds");
            echo "took " . (time() - $start) / 60 . " seconds\n";

            Log::doLog("sleeping for 1 month");
            echo "sleeping for 1 month\n";

            if ( self::$RUNNING ) {
                sleep( self::$sleeptime );
            }

        } while ( self::$RUNNING );
    }
}

$jpe = JobPEEAndTimeToEditRunner::getInstance();
/**
 * @var $jpe JobPEEAndTimeToEditRunner
 */
$jpe->main( null );
usleep(1);