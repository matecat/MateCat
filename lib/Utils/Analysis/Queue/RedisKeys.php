<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/05/15
 * Time: 13.37
 *
 */

namespace Analysis\Queue;

/**
 * Class RedisKeys
 * @package Analysis\Queue
 *
 * This class contains the constant strings used by the analysis to set/get values on Redis
 */
class RedisKeys {

    /**
     * FallBack for bugs on key name to not loose messages
     */
    const DEFAULT_QUEUE_NAME = " unknown_queue";

    /**
     * Key that holds the number of segments to wait before this job executed
     */
    const TOTAL_SEGMENTS_TO_WAIT = "seg_to_wait:";

    /**
     * Key that holds the total number of segments in the project
     */
    const PROJECT_TOT_SEGMENTS = 'p_tot_seg:';

    /**
     * Key that holds the number of segments already analyzed for this job
     */
    const PROJECT_NUM_SEGMENTS_DONE = 'p_num_done:';


    /**
     * Key that holds the incremental counter for the equivalent word count of the project
     */
    const PROJ_EQ_WORD_COUNT = 'eq_wc:';

    /**
     * Key that holds the incremental counter for the standard word count of the project
     */
    const PROJ_ST_WORD_COUNT = 'st_wc:';

    /**
     * Key Set that holds the main process of TM Analysis
     */
    const VOLUME_ANALYSIS_PID = 'tm_pid_set';

    /**
     * Key that holds the process ids of all fast analyses
     */
    const FAST_PID_LIST = 'fast_pid_list';

    /**
     * Key that holds the lock for the first analysis demon child
     * that start the analysis ( semaphore )
     */
    const PROJECT_INIT_SEMAPHORE = 'proj_init_lock:';

    /**
     * Key that holds the lock for the first analysis demon child
     * that reach the end of analysis( semaphore )
     */
    const PROJECT_ENDING_SEMAPHORE = 'proj_end_lock:';

}