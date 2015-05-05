<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 04/05/15
 * Time: 13.37
 * 
 */

class Constants_AnalysisRedisKeys {

    /**
     * Key that holds the number of segments to wait before this job executed
     */
    const TOTAL_SEGMENTS_TO_WAIT = "seg_to_wait:";

    /**
     * Key that holds the projects list in the queue
     */
    const PROJECTS_QUEUE_LIST = 'p_list';

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

}