<?php

/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 16/05/14
 * Time: 16.16
 *
 */
define( 'ANALYSIS_ROOT', INIT::$UTILS_ROOT . "/cron/volumeAnalysis" );

class Constants_Daemons {

    const NUM_PROCESSES      = 1;

    const PATH_TO_DAEMONS    = ANALYSIS_ROOT;
    const PID_FOLDER         = ".pidlist";
    const TM_MASTER_PID_FILE = 'TM_PID.pid';
    const NUM_PROCESSES_FILE = ".num_processes";
    const FAST_PID_FILE      = 'FAST_PID.pid';

}