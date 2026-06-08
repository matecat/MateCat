<?php
/**
 * Entry-point script for Executor child processes.
 * Spawned by TaskManager via pcntl_exec.
 *
 * Usage: /usr/bin/php executor_worker.php '{"queue_name":"...","max_executors":1,...}'
 */

use Utils\TaskRunner\Commons\Context;
use Utils\TaskRunner\Executor;

include_once realpath(dirname(__FILE__) . '/../../../') . "/lib/Bootstrap.php";
/** @noinspection PhpUnhandledExceptionInspection */
Bootstrap::start();

//$argv = array();
//$argv[ 1 ] = '{"queue_length":1,"queue_name":"mail_queue","pid_set_name":"ch_pid_set_mail","pid_list":[],"pid_list_len":0,"max_executors":"1","loggerName":"mail_queue.log"}';
//$argv[ 1 ] = '{"queue_length":0,"queue_name":"set_contribution","pid_set_name":"ch_pid_set_contribution","pid_list":[],"pid_list_len":0,"max_executors":"1","loggerName":"set_contribution.log"}';
//$argv[ 1 ] = '{"queue_name":"analysis_queue_P3","pid_set_name":"ch_pid_set_p3","max_executors":"1","redis_key":"p3_list","loggerName":"tm_analysis_P3.log"}';
//$argv[ 1 ] = '{"queue_name":"analysis_queue_P1","pid_set_name":"ch_pid_set_p1","max_executors":"1","redis_key":"p1_list","loggerName":"tm_analysis_P1.log"}';
//$argv[ 1 ] = '{"queue_name":"activity_log","pid_set_name":"ch_pid_activity_log","max_executors":"1","redis_key":"activity_log_list","loggerName":"activity_log.log"}';
//$argv[ 1 ] = '{"queue_name":"project_queue","pid_set_name":"ch_pid_project_queue","max_executors":"1","redis_key":"project_queue_list","loggerName":"project_queue.log"}';
//$argv[ 1 ] = '{"queue_name":"ai_assistant_explain_meaning","pid_set_name":"ch_pid_ai_assistant_explain_meaning","max_executors":"1","redis_key":"ai_assistant_explain_meaning","loggerName":"ai_assistant_explain_meaning.log"}';
//$argv[ 1 ] = '{"queue_length":0,"queue_name":"set_contribution_mt","pid_set_name":"ch_pid_set_contribution_mt","pid_list":[],"pid_list_len":0,"max_executors":"1","loggerName":"set_contribution_mt.log"}';
//$argv[ 1 ] = '{"queue_name":"jobs","pid_set_name":"ch_pid_jobs","max_executors":"1","redis_key":"jobs_list","loggerName":"jobs.log"}';
//$argv[ 1 ] = '{"queue_name":"qa_checks","pid_set_name":"qa_checks_set","max_executors":"1","redis_key":"qa_checks_key","loggerName":"qa_checks.log"}';
//$argv[ 1 ] = '{"queue_name":"get_contribution","pid_set_name":"ch_pid_get_contribution","max_executors":"1","redis_key":"get_contribution_list","loggerName":"get_contribution.log"}';
//$argv[ 1 ] = '{"queue_name":"aligner_align_job","pid_set_name":"ch_pid_align_job","max_executors":"1","redis_key":"align_job_list","loggerName":"align_job.log"}';
//$argv[ 1 ] = '{"queue_name":"aligner_tmx_import","pid_set_name":"ch_pid_tmx_import","max_executors":"1","redis_key":"tmx_import_list","loggerName":"tmx_import.log"}';
//$argv[ 1 ] = '{"queue_name":"aligner_segment_create","pid_set_name":"ch_pid_segment_create","max_executors":"1","redis_key":"segment_create_list","loggerName":"segment_create.log"}';

/** @var non-empty-list<string> $argv */
if (PHP_SAPI === 'cli' && isset($argv[1]) && ($__ctx = json_decode($argv[1], true)) !== null) {
    /** @noinspection PhpUnhandledExceptionInspection */
    Executor::getInstance(Context::buildFromArray($__ctx))->main();
}
