<?php

namespace Analysis\Commons;

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/05/15
 * Time: 13.37
 *
 */
class RedisKeys {

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
     * Key that holds the main process of TM Analysis
     */
    const VOLUME_ANALYSIS_PID = 'tm_pid';

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


    ##################################################
    ######          Queues Definition          #######
    ##################################################

    ##############################
    ###### Queue priority P1 #####
    ##############################
    /**
     * Key that holds the child set of the processes of TM Analysis in priority 1
     */
    const VA_CHILD_PID_SET_DEFAULT = 'ch_pid_set_p1';

    /**
     * Key that holds the projects list in the default queue
     */
    const PROJECTS_QUEUE_LIST_DEFAULT = 'p1_list';

    /**
     * Default queue name
     */
    const DEFAULT_QUEUE_NAME = 'analysis_queue_P1';

    ##############################
    ###### Queue priority P2 #####
    ##############################

    /**
     * Key that holds the child set of the processes of TM Analysis in priority 2
     */
    const VA_CHILD_PID_SET_P2 = 'ch_pid_set_p2';

    /**
     * Key that holds the projects list in the Priority 2 queue
     */
    const PROJECTS_QUEUE_LIST_P2 = 'p2_list';

    /**
     * Priority 2 queue name
     */
    const QUEUE_NAME_P2 = 'analysis_queue_P2';

    ##############################
    ###### Queue priority P3 #####
    ##############################

    /**
     * Key that holds the child set of the processes of TM Analysis in priority 2
     */
    const VA_CHILD_PID_SET_P3 = 'ch_pid_set_p3';

    /**
     * Key that holds the projects list in the Priority 2 queue
     */
    const PROJECTS_QUEUE_LIST_P3 = 'p3_list';

    /**
     * Priority 3 queue name
     */
    const QUEUE_NAME_P3 = 'analysis_queue_P3';

}