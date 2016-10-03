<?php

/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 20/02/14
 * Time: 11.47
 *
 */
class ServerCheck_mysqlParams extends ServerCheck_params {

    public function __set( $name, $value ) {
        if ( !property_exists( $this, $name ) ) {
            throw new DomainException( 'Unknown property ' . $name );
        }
    }
    protected $auto_increment_increment = null;
    protected $auto_increment_offset = null;
    protected $autocommit = null;
    protected $automatic_sp_privileges = null;
    protected $back_log = null;
    protected $basedir = null;
    protected $big_tables = null;
    protected $bind_address = null;
    protected $binlog_cache_size = null;
    protected $binlog_checksum = null;
    protected $binlog_direct_non_transactional_updates = null;
    protected $binlog_format = null;
    protected $binlog_max_flush_queue_time = null;
    protected $binlog_order_commits = null;
    protected $binlog_row_image = null;
    protected $binlog_rows_query_log_events = null;
    protected $binlog_stmt_cache_size = null;
    protected $binlog_group_commit_sync_delay = null;
    protected $binlog_group_commit_sync_no_delay_count = null;
    protected $block_encryption_mode = null;
    protected $bulk_insert_buffer_size = null;
    protected $character_set_client = null;
    protected $character_set_connection = null;
    protected $character_set_database = null;
    protected $character_set_filesystem = null;
    protected $character_set_results = null;
    protected $character_set_server = null;
    protected $character_set_system = null;
    protected $character_sets_dir = null;
    protected $collation_connection = null;
    protected $collation_database = null;
    protected $collation_server = null;
    protected $completion_type = null;
    protected $concurrent_insert = null;
    protected $connect_timeout = null;
    protected $core_file = null;
    protected $datadir = null;
    protected $date_format = null;
    protected $datetime_format = null;
    protected $default_storage_engine = null;
    protected $default_tmp_storage_engine = null;
    protected $default_week_format = null;
    protected $delay_key_write = null;
    protected $delayed_insert_limit = null;
    protected $delayed_insert_timeout = null;
    protected $delayed_queue_size = null;
    protected $disconnect_on_expired_password = null;
    protected $div_precision_increment = null;
    protected $end_markers_in_json = null;
    protected $enforce_gtid_consistency = null;
    protected $eq_range_index_dive_limit = null;
    protected $error_count = null;
    protected $event_scheduler = null;
    protected $expire_logs_days = null;
    protected $explicit_defaults_for_timestamp = null;
    protected $external_user = null;
    protected $flush = null;
    protected $flush_time = null;
    protected $foreign_key_checks = null;
    protected $ft_boolean_syntax = null;
    protected $ft_max_word_len = null;
    protected $ft_min_word_len = null;
    protected $ft_query_expansion_limit = null;
    protected $ft_stopword_file = null;
    protected $general_log = null;
    protected $general_log_file = null;
    protected $group_concat_max_len = null;
    protected $gtid_executed = null;
    protected $gtid_mode = null;
    protected $gtid_next = null;
    protected $gtid_owned = null;
    protected $gtid_purged = null;
    protected $have_compress = null;
    protected $have_crypt = null;
    protected $have_dynamic_loading = null;
    protected $have_geometry = null;
    protected $have_openssl = null;
    protected $have_profiling = null;
    protected $have_query_cache = null;
    protected $have_rtree_keys = null;
    protected $have_ssl = null;
    protected $have_symlink = null;
    protected $host_cache_size = null;
    protected $hostname = null;
    protected $identity = null;
    protected $ignore_builtin_innodb = null;
    protected $ignore_db_dirs = null;
    protected $init_connect = null;
    protected $init_file = null;
    protected $init_slave = null;
    protected $innodb_adaptive_flushing = null;
    protected $innodb_adaptive_flushing_lwm = null;
    protected $innodb_adaptive_hash_index = null;
    protected $innodb_adaptive_max_sleep_delay = null;
    protected $innodb_additional_mem_pool_size = null;
    protected $innodb_api_bk_commit_interval = null;
    protected $innodb_api_disable_rowlock = null;
    protected $innodb_api_enable_binlog = null;
    protected $innodb_api_enable_mdl = null;
    protected $innodb_api_trx_level = null;
    protected $innodb_autoextend_increment = null;
    protected $innodb_autoinc_lock_mode = null;
    protected $innodb_buffer_pool_dump_at_shutdown = null;
    protected $innodb_buffer_pool_dump_now = null;
    protected $innodb_buffer_pool_filename = null;
    protected $innodb_buffer_pool_instances = null;
    protected $innodb_buffer_pool_load_abort = null;
    protected $innodb_buffer_pool_load_at_startup = null;
    protected $innodb_buffer_pool_load_now = null;
    protected $innodb_buffer_pool_size = null;
    protected $innodb_change_buffer_max_size = null;
    protected $innodb_change_buffering = null;
    protected $innodb_checksum_algorithm = null;
    protected $innodb_checksums = null;
    protected $innodb_cmp_per_index_enabled = null;
    protected $innodb_commit_concurrency = null;
    protected $innodb_compression_failure_threshold_pct = null;
    protected $innodb_compression_level = null;
    protected $innodb_compression_pad_pct_max = null;
    protected $innodb_concurrency_tickets = null;
    protected $innodb_data_file_path = null;
    protected $innodb_data_home_dir = null;
    protected $innodb_disable_sort_file_cache = null;
    protected $innodb_doublewrite = null;
    protected $innodb_fast_shutdown = null;
    protected $innodb_file_format = null;
    protected $innodb_file_format_check = null;
    protected $innodb_file_format_max = null;
    protected $innodb_file_per_table = null;
    protected $innodb_flush_log_at_timeout = null;
    protected $innodb_flush_log_at_trx_commit = null;
    protected $innodb_flush_method = null;
    protected $innodb_flush_neighbors = null;
    protected $innodb_flushing_avg_loops = null;
    protected $innodb_force_load_corrupted = null;
    protected $innodb_force_recovery = null;
    protected $innodb_ft_aux_table = null;
    protected $innodb_ft_cache_size = null;
    protected $innodb_ft_enable_diag_print = null;
    protected $innodb_ft_enable_stopword = null;
    protected $innodb_ft_max_token_size = null;
    protected $innodb_ft_min_token_size = null;
    protected $innodb_ft_num_word_optimize = null;
    protected $innodb_ft_result_cache_limit = null;
    protected $innodb_ft_server_stopword_table = null;
    protected $innodb_ft_sort_pll_degree = null;
    protected $innodb_ft_total_cache_size = null;
    protected $innodb_ft_user_stopword_table = null;
    protected $innodb_io_capacity = null;
    protected $innodb_io_capacity_max = null;
    protected $innodb_large_prefix = null;
    protected $innodb_lock_wait_timeout = null;
    protected $innodb_locks_unsafe_for_binlog = null;
    protected $innodb_log_buffer_size = null;
    protected $innodb_log_compressed_pages = null;
    protected $innodb_log_file_size = null;
    protected $innodb_log_files_in_group = null;
    protected $innodb_log_group_home_dir = null;
    protected $innodb_lru_scan_depth = null;
    protected $innodb_max_dirty_pages_pct = null;
    protected $innodb_max_dirty_pages_pct_lwm = null;
    protected $innodb_max_purge_lag = null;
    protected $innodb_max_purge_lag_delay = null;
    protected $innodb_mirrored_log_groups = null;
    protected $innodb_monitor_disable = null;
    protected $innodb_monitor_enable = null;
    protected $innodb_monitor_reset = null;
    protected $innodb_monitor_reset_all = null;
    protected $innodb_old_blocks_pct = null;
    protected $innodb_old_blocks_time = null;
    protected $innodb_online_alter_log_max_size = null;
    protected $innodb_open_files = null;
    protected $innodb_optimize_fulltext_only = null;
    protected $innodb_page_size = null;
    protected $innodb_print_all_deadlocks = null;
    protected $innodb_purge_batch_size = null;
    protected $innodb_purge_threads = null;
    protected $innodb_random_read_ahead = null;
    protected $innodb_read_ahead_threshold = null;
    protected $innodb_read_io_threads = null;
    protected $innodb_read_only = null;
    protected $innodb_replication_delay = null;
    protected $innodb_rollback_on_timeout = null;
    protected $innodb_rollback_segments = null;
    protected $innodb_sort_buffer_size = null;
    protected $innodb_spin_wait_delay = null;
    protected $innodb_stats_auto_recalc = null;
    protected $innodb_stats_method = null;
    protected $innodb_stats_on_metadata = null;
    protected $innodb_stats_persistent = null;
    protected $innodb_stats_persistent_sample_pages = null;
    protected $innodb_stats_sample_pages = null;
    protected $innodb_stats_transient_sample_pages = null;
    protected $innodb_status_output = null;
    protected $innodb_status_output_locks = null;
    protected $innodb_strict_mode = null;
    protected $innodb_support_xa = null;
    protected $innodb_sync_array_size = null;
    protected $innodb_sync_spin_loops = null;
    protected $innodb_table_locks = null;
    protected $innodb_thread_concurrency = null;
    protected $innodb_thread_sleep_delay = null;
    protected $innodb_undo_directory = null;
    protected $innodb_undo_logs = null;
    protected $innodb_undo_tablespaces = null;
    protected $innodb_use_native_aio = null;
    protected $innodb_use_sys_malloc = null;
    protected $innodb_version = null;
    protected $innodb_write_io_threads = null;
    protected $innodb_adaptive_hash_index_parts = null;
    protected $innodb_buffer_pool_chunk_size = null;
    protected $innodb_buffer_pool_dump_pct = null;
    protected $innodb_default_row_format = null;
    protected $innodb_fill_factor = null;
    protected $innodb_flush_sync = null;
    protected $innodb_log_checksums = null;
    protected $innodb_log_write_ahead_size = null;
    protected $innodb_max_undo_log_size = null;
    protected $innodb_page_cleaners = null;
    protected $innodb_purge_rseg_truncate_frequency = null;
    protected $innodb_temp_data_file_path = null;
    protected $innodb_tmpdir = null;
    protected $innodb_undo_log_truncate = null;
    protected $internal_tmp_disk_storage_engine = null;
    protected $log_builtin_as_identified_by_password = null;
    protected $log_error_verbosity = null;
    protected $log_statements_unsafe_for_binlog = null;
    protected $log_syslog = null;
    protected $log_syslog_facility = null;
    protected $log_syslog_include_pid = null;
    protected $log_syslog_tag = null;
    protected $log_timestamps = null;
    protected $max_digest_length = null;
    protected $max_execution_time = null;
    protected $max_points_in_geometry = null;
    protected $mysql_native_password_proxy_users = null;
    protected $ngram_token_size = null;
    protected $offline_mode = null;
    protected $parser_max_mem_size = null;
    protected $performance_schema                                       = null;
    protected $performance_schema_accounts_size                         = null;
    protected $performance_schema_digests_size                          = null;
    protected $performance_schema_events_stages_history_long_size       = null;
    protected $performance_schema_events_stages_history_size            = null;
    protected $performance_schema_events_statements_history_long_size   = null;
    protected $performance_schema_events_statements_history_size        = null;
    protected $performance_schema_events_transactions_history_long_size = null;
    protected $performance_schema_events_transactions_history_size      = null;
    protected $performance_schema_events_waits_history_long_size        = null;
    protected $performance_schema_events_waits_history_size             = null;
    protected $performance_schema_hosts_size                            = null;
    protected $performance_schema_max_cond_classes                      = null;
    protected $performance_schema_max_cond_instances                    = null;
    protected $performance_schema_max_digest_length                     = null;
    protected $performance_schema_max_file_classes                      = null;
    protected $performance_schema_max_file_handles                      = null;
    protected $performance_schema_max_file_instances                    = null;
    protected $performance_schema_max_index_stat                        = null;
    protected $performance_schema_max_memory_classes                    = null;
    protected $performance_schema_max_metadata_locks                    = null;
    protected $performance_schema_max_mutex_classes                     = null;
    protected $performance_schema_max_mutex_instances                   = null;
    protected $performance_schema_max_prepared_statements_instances     = null;
    protected $performance_schema_max_program_instances                 = null;
    protected $performance_schema_max_rwlock_classes                    = null;
    protected $performance_schema_max_rwlock_instances                  = null;
    protected $performance_schema_max_socket_classes                    = null;
    protected $performance_schema_max_socket_instances                  = null;
    protected $performance_schema_max_sql_text_length                   = null;
    protected $performance_schema_max_stage_classes                     = null;
    protected $performance_schema_max_statement_classes                 = null;
    protected $performance_schema_max_statement_stack                   = null;
    protected $performance_schema_max_table_handles                     = null;
    protected $performance_schema_max_table_instances                   = null;
    protected $performance_schema_max_table_lock_stat                   = null;
    protected $performance_schema_max_thread_classes                    = null;
    protected $performance_schema_max_thread_instances                  = null;
    protected $performance_schema_session_connect_attrs_size            = null;
    protected $performance_schema_setup_actors_size                     = null;
    protected $performance_schema_setup_objects_size                    = null;
    protected $performance_schema_users_size                            = null;
    protected $insert_id = null;
    protected $interactive_timeout = null;
    protected $join_buffer_size = null;
    protected $keep_files_on_create = null;
    protected $key_buffer_size = null;
    protected $key_cache_age_threshold = null;
    protected $key_cache_block_size = null;
    protected $key_cache_division_limit = null;
    protected $large_files_support = null;
    protected $large_page_size = null;
    protected $large_pages = null;
    protected $last_insert_id = null;
    protected $lc_messages = null;
    protected $lc_messages_dir = null;
    protected $lc_time_names = null;
    protected $license = null;
    protected $local_infile = null;
    protected $lock_wait_timeout = null;
    protected $locked_in_memory = null;
    protected $log_bin = null;
    protected $log_bin_basename = null;
    protected $log_bin_index = null;
    protected $log_bin_trust_function_creators = null;
    protected $log_bin_use_v1_row_events = null;
    protected $log_error = null;
    protected $log_output = null;
    protected $log_queries_not_using_indexes = null;
    protected $log_slave_updates = null;
    protected $log_slow_admin_statements = null;
    protected $log_slow_slave_statements = null;
    protected $log_throttle_queries_not_using_indexes = null;
    protected $log_warnings = null;
    protected $long_query_time = null;
    protected $low_priority_updates = null;
    protected $lower_case_file_system = null;
    protected $lower_case_table_names = null;
    protected $master_info_repository = null;
    protected $master_verify_checksum = null;
    protected $max_allowed_packet = null;
    protected $max_binlog_cache_size = null;
    protected $max_binlog_size = null;
    protected $max_binlog_stmt_cache_size = null;
    protected $max_connect_errors = null;
    protected $max_connections = null;
    protected $max_delayed_threads = null;
    protected $max_error_count = null;
    protected $max_heap_table_size = null;
    protected $max_insert_delayed_threads = null;
    protected $max_join_size = null;
    protected $max_length_for_sort_data = null;
    protected $max_prepared_stmt_count = null;
    protected $max_relay_log_size = null;
    protected $max_seeks_for_key = null;
    protected $max_sort_length = null;
    protected $max_sp_recursion_depth = null;
    protected $max_tmp_tables = null;
    protected $max_user_connections = null;
    protected $max_write_lock_count = null;
    protected $metadata_locks_cache_size = null;
    protected $metadata_locks_hash_instances = null;
    protected $min_examined_row_limit = null;
    protected $multi_range_count = null;
    protected $myisam_data_pointer_size = null;
    protected $myisam_max_sort_file_size = null;
    protected $myisam_mmap_size = null;
    protected $myisam_recover_options = null;
    protected $myisam_repair_threads = null;
    protected $myisam_sort_buffer_size = null;
    protected $myisam_stats_method = null;
    protected $myisam_use_mmap = null;
    protected $net_buffer_length = null;
    protected $net_read_timeout = null;
    protected $net_retry_count = null;
    protected $net_write_timeout = null;
    protected $new = null;
    protected $old = null;
    protected $old_alter_table = null;
    protected $old_passwords = null;
    protected $open_files_limit = null;
    protected $optimizer_prune_level = null;
    protected $optimizer_search_depth = null;
    protected $optimizer_switch = null;
    protected $optimizer_trace = null;
    protected $optimizer_trace_features = null;
    protected $optimizer_trace_limit = null;
    protected $optimizer_trace_max_mem_size = null;
    protected $optimizer_trace_offset = null;
    protected $pid_file = null;
    protected $plugin_dir = null;
    protected $port = null;
    protected $preload_buffer_size = null;
    protected $profiling = null;
    protected $profiling_history_size = null;
    protected $protocol_version = null;
    protected $proxy_user = null;
    protected $check_proxy_users = null;
    protected $pseudo_slave_mode = null;
    protected $pseudo_thread_id = null;
    protected $query_alloc_block_size = null;
    protected $query_cache_limit = null;
    protected $query_cache_min_res_unit = null;
    protected $query_cache_size = null;
    protected $query_cache_type = null;
    protected $query_cache_wlock_invalidate = null;
    protected $query_prealloc_size = null;
    protected $rand_seed1 = null;
    protected $rand_seed2 = null;
    protected $range_alloc_block_size = null;
    protected $range_optimizer_max_mem_size = null;
    protected $read_buffer_size = null;
    protected $read_only = null;
    protected $read_rnd_buffer_size = null;
    protected $rbr_exec_mode = null;
    protected $relay_log = null;
    protected $relay_log_basename = null;
    protected $relay_log_index = null;
    protected $relay_log_info_file = null;
    protected $relay_log_info_repository = null;
    protected $relay_log_purge = null;
    protected $relay_log_recovery = null;
    protected $relay_log_space_limit = null;
    protected $report_host = null;
    protected $report_password = null;
    protected $report_port = null;
    protected $report_user = null;
    protected $require_secure_transport = null;
    protected $rpl_stop_slave_timeout = null;
    protected $default_authentication_plugin = null;
    protected $default_password_lifetime = null;
    protected $secure_auth = null;
    protected $secure_file_priv = null;
    protected $server_id = null;
    protected $server_id_bits = null;
    protected $server_uuid = null;
    protected $session_track_gtids            = null;
    protected $session_track_schema           = null;
    protected $session_track_state_change     = null;
    protected $session_track_system_variables = null;
    protected $session_track_transaction_info = null;
    protected $sha256_password_proxy_users = null;
    protected $show_compatibility_56 = null;
    protected $show_old_temporals = null;
    protected $skip_external_locking = null;
    protected $skip_name_resolve = null;
    protected $skip_networking = null;
    protected $skip_show_database = null;
    protected $slave_allow_batching = null;
    protected $slave_checkpoint_group = null;
    protected $slave_checkpoint_period = null;
    protected $slave_compressed_protocol = null;
    protected $slave_exec_mode = null;
    protected $slave_load_tmpdir = null;
    protected $slave_max_allowed_packet = null;
    protected $slave_net_timeout = null;
    protected $slave_parallel_workers = null;
    protected $slave_parallel_type = null;
    protected $slave_pending_jobs_size_max = null;
    protected $slave_preserve_commit_order = null;
    protected $slave_rows_search_algorithms = null;
    protected $slave_skip_errors = null;
    protected $slave_sql_verify_checksum = null;
    protected $slave_transaction_retries = null;
    protected $slave_type_conversions = null;
    protected $slow_launch_time = null;
    protected $slow_query_log = null;
    protected $slow_query_log_file = null;
    protected $socket = null;
    protected $sort_buffer_size = null;
    protected $sql_auto_is_null = null;
    protected $sql_big_selects = null;
    protected $sql_buffer_result = null;
    protected $sql_log_bin = null;
    protected $sql_log_off = null;
    protected $sql_mode = null;
    protected $sql_notes = null;
    protected $sql_quote_show_create = null;
    protected $sql_safe_updates = null;
    protected $sql_select_limit = null;
    protected $sql_slave_skip_counter = null;
    protected $sql_warnings = null;
    protected $ssl_ca = null;
    protected $ssl_capath = null;
    protected $ssl_cert = null;
    protected $ssl_cipher = null;
    protected $ssl_crl = null;
    protected $ssl_crlpath = null;
    protected $ssl_key = null;
    protected $storage_engine = null;
    protected $super_read_only = null;
    protected $disabled_storage_engines = null;
    protected $stored_program_cache = null;
    protected $sync_binlog = null;
    protected $sync_frm = null;
    protected $sync_master_info = null;
    protected $sync_relay_log = null;
    protected $sync_relay_log_info = null;
    protected $system_time_zone = null;
    protected $table_definition_cache = null;
    protected $table_open_cache = null;
    protected $table_open_cache_instances = null;
    protected $thread_cache_size = null;
    protected $thread_concurrency = null;
    protected $thread_handling = null;
    protected $thread_stack = null;
    protected $time_format = null;
    protected $time_zone = null;
    protected $timed_mutexes = null;
    protected $timestamp = null;
    protected $tls_version = null;
    protected $tmp_table_size = null;
    protected $tmpdir = null;
    protected $transaction_alloc_block_size = null;
    protected $transaction_allow_batching = null;
    protected $transaction_prealloc_size = null;
    protected $transaction_write_set_extraction = null;
    protected $tx_isolation = null;
    protected $tx_read_only = null;
    protected $unique_checks = null;
    protected $updatable_views_with_limit = null;
    protected $version = null;
    protected $version_comment = null;
    protected $version_compile_machine = null;
    protected $version_compile_os = null;
    protected $wait_timeout = null;
    protected $warning_count = null;

    protected $avoid_temporal_upgrade = null;
    protected $binlog_error_action = null;
    protected $binlog_gtid_simple_recovery = null;
    protected $gtid_executed_compression_period = null;
    protected $have_statement_timeout = null;

    /**
     * @return null
     */
    public function getBinlogGtidSimpleRecovery()
    {
        return $this->binlog_gtid_simple_recovery;
    }

    /**
     * @return null
     */
    public function getBinlogErrorAction()
    {
        return $this->binlog_error_action;
    }


    /**
     * @return null
     */
    public function getAvoidTemporalUpgrade()
    {
        return $this->avoid_temporal_upgrade;
    }

    /**
     * @return null
     */
    public function getAutoIncrementIncrement() {
        return $this->auto_increment_increment;
    }

    /**
     * @return null
     */
    public function getAutoIncrementOffset() {
        return $this->auto_increment_offset;
    }

    /**
     * @return null
     */
    public function getAutocommit() {
        return $this->autocommit;
    }

    /**
     * @return null
     */
    public function getAutomaticSpPrivileges() {
        return $this->automatic_sp_privileges;
    }

    /**
     * @return null
     */
    public function getBackLog() {
        return $this->back_log;
    }

    /**
     * @return null
     */
    public function getBasedir() {
        return $this->basedir;
    }

    /**
     * @return null
     */
    public function getBigTables() {
        return $this->big_tables;
    }

    /**
     * @return null
     */
    public function getBindAddress() {
        return $this->bind_address;
    }

    /**
     * @return null
     */
    public function getBinlogCacheSize() {
        return $this->binlog_cache_size;
    }

    /**
     * @return null
     */
    public function getBinlogChecksum() {
        return $this->binlog_checksum;
    }

    /**
     * @return null
     */
    public function getBinlogDirectNonTransactionalUpdates() {
        return $this->binlog_direct_non_transactional_updates;
    }

    /**
     * @return null
     */
    public function getBinlogFormat() {
        return $this->binlog_format;
    }

    /**
     * @return null
     */
    public function getBinlogMaxFlushQueueTime() {
        return $this->binlog_max_flush_queue_time;
    }

    /**
     * @return null
     */
    public function getBinlogOrderCommits() {
        return $this->binlog_order_commits;
    }

    /**
     * @return null
     */
    public function getBinlogRowImage() {
        return $this->binlog_row_image;
    }

    /**
     * @return null
     */
    public function getBinlogRowsQueryLogEvents() {
        return $this->binlog_rows_query_log_events;
    }

    /**
     * @return null
     */
    public function getBinlogStmtCacheSize() {
        return $this->binlog_stmt_cache_size;
    }

    /**
     * @return null
     */
    public function getBulkInsertBufferSize() {
        return $this->bulk_insert_buffer_size;
    }

    /**
     * @return null
     */
    public function getCharacterSetClient() {
        return $this->character_set_client;
    }

    /**
     * @return null
     */
    public function getCharacterSetConnection() {
        return $this->character_set_connection;
    }

    /**
     * @return null
     */
    public function getCharacterSetDatabase() {
        return $this->character_set_database;
    }

    /**
     * @return null
     */
    public function getCharacterSetFilesystem() {
        return $this->character_set_filesystem;
    }

    /**
     * @return null
     */
    public function getCharacterSetResults() {
        return $this->character_set_results;
    }

    /**
     * @return null
     */
    public function getCharacterSetServer() {
        return $this->character_set_server;
    }

    /**
     * @return null
     */
    public function getCharacterSetSystem() {
        return $this->character_set_system;
    }

    /**
     * @return null
     */
    public function getCharacterSetsDir() {
        return $this->character_sets_dir;
    }

    /**
     * @return null
     */
    public function getCollationConnection() {
        return $this->collation_connection;
    }

    /**
     * @return null
     */
    public function getCollationDatabase() {
        return $this->collation_database;
    }

    /**
     * @return null
     */
    public function getCollationServer() {
        return $this->collation_server;
    }

    /**
     * @return null
     */
    public function getCompletionType() {
        return $this->completion_type;
    }

    /**
     * @return null
     */
    public function getConcurrentInsert() {
        return $this->concurrent_insert;
    }

    /**
     * @return null
     */
    public function getConnectTimeout() {
        return $this->connect_timeout;
    }

    /**
     * @return null
     */
    public function getCoreFile() {
        return $this->core_file;
    }

    /**
     * @return null
     */
    public function getDatadir() {
        return $this->datadir;
    }

    /**
     * @return null
     */
    public function getDateFormat() {
        return $this->date_format;
    }

    /**
     * @return null
     */
    public function getDatetimeFormat() {
        return $this->datetime_format;
    }

    /**
     * @return null
     */
    public function getDefaultStorageEngine() {
        return $this->default_storage_engine;
    }

    /**
     * @return null
     */
    public function getDefaultTmpStorageEngine() {
        return $this->default_tmp_storage_engine;
    }

    /**
     * @return null
     */
    public function getDefaultWeekFormat() {
        return $this->default_week_format;
    }

    /**
     * @return null
     */
    public function getDelayKeyWrite() {
        return $this->delay_key_write;
    }

    /**
     * @return null
     */
    public function getDelayedInsertLimit() {
        return $this->delayed_insert_limit;
    }

    /**
     * @return null
     */
    public function getDelayedInsertTimeout() {
        return $this->delayed_insert_timeout;
    }

    /**
     * @return null
     */
    public function getDelayedQueueSize() {
        return $this->delayed_queue_size;
    }

    /**
     * @return null
     */
    public function getDisconnectOnExpiredPassword() {
        return $this->disconnect_on_expired_password;
    }

    /**
     * @return null
     */
    public function getDivPrecisionIncrement() {
        return $this->div_precision_increment;
    }

    /**
     * @return null
     */
    public function getEndMarkersInJson() {
        return $this->end_markers_in_json;
    }

    /**
     * @return null
     */
    public function getEnforceGtidConsistency() {
        return $this->enforce_gtid_consistency;
    }

    /**
     * @return null
     */
    public function getEqRangeIndexDiveLimit() {
        return $this->eq_range_index_dive_limit;
    }

    /**
     * @return null
     */
    public function getErrorCount() {
        return $this->error_count;
    }

    /**
     * @return null
     */
    public function getEventScheduler() {
        return $this->event_scheduler;
    }

    /**
     * @return null
     */
    public function getExpireLogsDays() {
        return $this->expire_logs_days;
    }

    /**
     * @return null
     */
    public function getExplicitDefaultsForTimestamp() {
        return $this->explicit_defaults_for_timestamp;
    }

    /**
     * @return null
     */
    public function getExternalUser() {
        return $this->external_user;
    }

    /**
     * @return null
     */
    public function getFlush() {
        return $this->flush;
    }

    /**
     * @return null
     */
    public function getFlushTime() {
        return $this->flush_time;
    }

    /**
     * @return null
     */
    public function getForeignKeyChecks() {
        return $this->foreign_key_checks;
    }

    /**
     * @return null
     */
    public function getFtBooleanSyntax() {
        return $this->ft_boolean_syntax;
    }

    /**
     * @return null
     */
    public function getFtMaxWordLen() {
        return $this->ft_max_word_len;
    }

    /**
     * @return null
     */
    public function getFtMinWordLen() {
        return $this->ft_min_word_len;
    }

    /**
     * @return null
     */
    public function getFtQueryExpansionLimit() {
        return $this->ft_query_expansion_limit;
    }

    /**
     * @return null
     */
    public function getFtStopwordFile() {
        return $this->ft_stopword_file;
    }

    /**
     * @return null
     */
    public function getGeneralLog() {
        return $this->general_log;
    }

    /**
     * @return null
     */
    public function getGeneralLogFile() {
        return $this->general_log_file;
    }

    /**
     * @return null
     */
    public function getGroupConcatMaxLen() {
        return $this->group_concat_max_len;
    }

    /**
     * @return null
     */
    public function getGtidExecuted() {
        return $this->gtid_executed;
    }

    /**
     * @return null
     */
    public function getGtidMode() {
        return $this->gtid_mode;
    }

    /**
     * @return null
     */
    public function getGtidNext() {
        return $this->gtid_next;
    }

    /**
     * @return null
     */
    public function getGtidOwned() {
        return $this->gtid_owned;
    }

    /**
     * @return null
     */
    public function getGtidPurged() {
        return $this->gtid_purged;
    }

    /**
     * @return null
     */
    public function getHaveCompress() {
        return $this->have_compress;
    }

    /**
     * @return null
     */
    public function getHaveCrypt() {
        return $this->have_crypt;
    }

    /**
     * @return null
     */
    public function getHaveDynamicLoading() {
        return $this->have_dynamic_loading;
    }

    /**
     * @return null
     */
    public function getHaveGeometry() {
        return $this->have_geometry;
    }

    /**
     * @return null
     */
    public function getHaveOpenssl() {
        return $this->have_openssl;
    }

    /**
     * @return null
     */
    public function getHaveProfiling() {
        return $this->have_profiling;
    }

    /**
     * @return null
     */
    public function getHaveQueryCache() {
        return $this->have_query_cache;
    }

    /**
     * @return null
     */
    public function getHaveRtreeKeys() {
        return $this->have_rtree_keys;
    }

    /**
     * @return null
     */
    public function getHaveSsl() {
        return $this->have_ssl;
    }

    /**
     * @return null
     */
    public function getHaveSymlink() {
        return $this->have_symlink;
    }

    /**
     * @return null
     */
    public function getHostCacheSize() {
        return $this->host_cache_size;
    }

    /**
     * @return null
     */
    public function getHostname() {
        return $this->hostname;
    }

    /**
     * @return null
     */
    public function getIdentity() {
        return $this->identity;
    }

    /**
     * @return null
     */
    public function getIgnoreBuiltinInnodb() {
        return $this->ignore_builtin_innodb;
    }

    /**
     * @return null
     */
    public function getIgnoreDbDirs() {
        return $this->ignore_db_dirs;
    }

    /**
     * @return null
     */
    public function getInitConnect() {
        return $this->init_connect;
    }

    /**
     * @return null
     */
    public function getInitFile() {
        return $this->init_file;
    }

    /**
     * @return null
     */
    public function getInitSlave() {
        return $this->init_slave;
    }

    /**
     * @return null
     */
    public function getInnodbAdaptiveFlushing() {
        return $this->innodb_adaptive_flushing;
    }

    /**
     * @return null
     */
    public function getInnodbAdaptiveFlushingLwm() {
        return $this->innodb_adaptive_flushing_lwm;
    }

    /**
     * @return null
     */
    public function getInnodbAdaptiveHashIndex() {
        return $this->innodb_adaptive_hash_index;
    }

    /**
     * @return null
     */
    public function getInnodbAdaptiveMaxSleepDelay() {
        return $this->innodb_adaptive_max_sleep_delay;
    }

    /**
     * @return null
     */
    public function getInnodbAdditionalMemPoolSize() {
        return $this->innodb_additional_mem_pool_size;
    }

    /**
     * @return null
     */
    public function getInnodbApiBkCommitInterval() {
        return $this->innodb_api_bk_commit_interval;
    }

    /**
     * @return null
     */
    public function getInnodbApiDisableRowlock() {
        return $this->innodb_api_disable_rowlock;
    }

    /**
     * @return null
     */
    public function getInnodbApiEnableBinlog() {
        return $this->innodb_api_enable_binlog;
    }

    /**
     * @return null
     */
    public function getInnodbApiEnableMdl() {
        return $this->innodb_api_enable_mdl;
    }

    /**
     * @return null
     */
    public function getInnodbApiTrxLevel() {
        return $this->innodb_api_trx_level;
    }

    /**
     * @return null
     */
    public function getInnodbAutoextendIncrement() {
        return $this->innodb_autoextend_increment;
    }

    /**
     * @return null
     */
    public function getInnodbAutoincLockMode() {
        return $this->innodb_autoinc_lock_mode;
    }

    /**
     * @return null
     */
    public function getInnodbBufferPoolDumpAtShutdown() {
        return $this->innodb_buffer_pool_dump_at_shutdown;
    }

    /**
     * @return null
     */
    public function getInnodbBufferPoolDumpNow() {
        return $this->innodb_buffer_pool_dump_now;
    }

    /**
     * @return null
     */
    public function getInnodbBufferPoolFilename() {
        return $this->innodb_buffer_pool_filename;
    }

    /**
     * @return null
     */
    public function getInnodbBufferPoolInstances() {
        return $this->innodb_buffer_pool_instances;
    }

    /**
     * @return null
     */
    public function getInnodbBufferPoolLoadAbort() {
        return $this->innodb_buffer_pool_load_abort;
    }

    /**
     * @return null
     */
    public function getInnodbBufferPoolLoadAtStartup() {
        return $this->innodb_buffer_pool_load_at_startup;
    }

    /**
     * @return null
     */
    public function getInnodbBufferPoolLoadNow() {
        return $this->innodb_buffer_pool_load_now;
    }

    /**
     * @return null
     */
    public function getInnodbBufferPoolSize() {
        return $this->innodb_buffer_pool_size;
    }

    /**
     * @return null
     */
    public function getInnodbChangeBufferMaxSize() {
        return $this->innodb_change_buffer_max_size;
    }

    /**
     * @return null
     */
    public function getInnodbChangeBuffering() {
        return $this->innodb_change_buffering;
    }

    /**
     * @return null
     */
    public function getInnodbChecksumAlgorithm() {
        return $this->innodb_checksum_algorithm;
    }

    /**
     * @return null
     */
    public function getInnodbChecksums() {
        return $this->innodb_checksums;
    }

    /**
     * @return null
     */
    public function getInnodbCmpPerIndexEnabled() {
        return $this->innodb_cmp_per_index_enabled;
    }

    /**
     * @return null
     */
    public function getInnodbCommitConcurrency() {
        return $this->innodb_commit_concurrency;
    }

    /**
     * @return null
     */
    public function getInnodbCompressionFailureThresholdPct() {
        return $this->innodb_compression_failure_threshold_pct;
    }

    /**
     * @return null
     */
    public function getInnodbCompressionLevel() {
        return $this->innodb_compression_level;
    }

    /**
     * @return null
     */
    public function getInnodbCompressionPadPctMax() {
        return $this->innodb_compression_pad_pct_max;
    }

    /**
     * @return null
     */
    public function getInnodbConcurrencyTickets() {
        return $this->innodb_concurrency_tickets;
    }

    /**
     * @return null
     */
    public function getInnodbDataFilePath() {
        return $this->innodb_data_file_path;
    }

    /**
     * @return null
     */
    public function getInnodbDataHomeDir() {
        return $this->innodb_data_home_dir;
    }

    /**
     * @return null
     */
    public function getInnodbDisableSortFileCache() {
        return $this->innodb_disable_sort_file_cache;
    }

    /**
     * @return null
     */
    public function getInnodbDoublewrite() {
        return $this->innodb_doublewrite;
    }

    /**
     * @return null
     */
    public function getInnodbFastShutdown() {
        return $this->innodb_fast_shutdown;
    }

    /**
     * @return null
     */
    public function getInnodbFileFormat() {
        return $this->innodb_file_format;
    }

    /**
     * @return null
     */
    public function getInnodbFileFormatCheck() {
        return $this->innodb_file_format_check;
    }

    /**
     * @return null
     */
    public function getInnodbFileFormatMax() {
        return $this->innodb_file_format_max;
    }

    /**
     * @return null
     */
    public function getInnodbFilePerTable() {
        return $this->innodb_file_per_table;
    }

    /**
     * @return null
     */
    public function getInnodbFlushLogAtTimeout() {
        return $this->innodb_flush_log_at_timeout;
    }

    /**
     * @return null
     */
    public function getInnodbFlushLogAtTrxCommit() {
        return $this->innodb_flush_log_at_trx_commit;
    }

    /**
     * @return null
     */
    public function getInnodbFlushMethod() {
        return $this->innodb_flush_method;
    }

    /**
     * @return null
     */
    public function getInnodbFlushNeighbors() {
        return $this->innodb_flush_neighbors;
    }

    /**
     * @return null
     */
    public function getInnodbFlushingAvgLoops() {
        return $this->innodb_flushing_avg_loops;
    }

    /**
     * @return null
     */
    public function getInnodbForceLoadCorrupted() {
        return $this->innodb_force_load_corrupted;
    }

    /**
     * @return null
     */
    public function getInnodbForceRecovery() {
        return $this->innodb_force_recovery;
    }

    /**
     * @return null
     */
    public function getInnodbFtAuxTable() {
        return $this->innodb_ft_aux_table;
    }

    /**
     * @return null
     */
    public function getInnodbFtCacheSize() {
        return $this->innodb_ft_cache_size;
    }

    /**
     * @return null
     */
    public function getInnodbFtEnableDiagPrint() {
        return $this->innodb_ft_enable_diag_print;
    }

    /**
     * @return null
     */
    public function getInnodbFtEnableStopword() {
        return $this->innodb_ft_enable_stopword;
    }

    /**
     * @return null
     */
    public function getInnodbFtMaxTokenSize() {
        return $this->innodb_ft_max_token_size;
    }

    /**
     * @return null
     */
    public function getInnodbFtMinTokenSize() {
        return $this->innodb_ft_min_token_size;
    }

    /**
     * @return null
     */
    public function getInnodbFtNumWordOptimize() {
        return $this->innodb_ft_num_word_optimize;
    }

    /**
     * @return null
     */
    public function getInnodbFtResultCacheLimit() {
        return $this->innodb_ft_result_cache_limit;
    }

    /**
     * @return null
     */
    public function getInnodbFtServerStopwordTable() {
        return $this->innodb_ft_server_stopword_table;
    }

    /**
     * @return null
     */
    public function getInnodbFtSortPllDegree() {
        return $this->innodb_ft_sort_pll_degree;
    }

    /**
     * @return null
     */
    public function getInnodbFtTotalCacheSize() {
        return $this->innodb_ft_total_cache_size;
    }

    /**
     * @return null
     */
    public function getInnodbFtUserStopwordTable() {
        return $this->innodb_ft_user_stopword_table;
    }

    /**
     * @return null
     */
    public function getInnodbIoCapacity() {
        return $this->innodb_io_capacity;
    }

    /**
     * @return null
     */
    public function getInnodbIoCapacityMax() {
        return $this->innodb_io_capacity_max;
    }

    /**
     * @return null
     */
    public function getInnodbLargePrefix() {
        return $this->innodb_large_prefix;
    }

    /**
     * @return null
     */
    public function getInnodbLockWaitTimeout() {
        return $this->innodb_lock_wait_timeout;
    }

    /**
     * @return null
     */
    public function getInnodbLocksUnsafeForBinlog() {
        return $this->innodb_locks_unsafe_for_binlog;
    }

    /**
     * @return null
     */
    public function getInnodbLogBufferSize() {
        return $this->innodb_log_buffer_size;
    }

    /**
     * @return null
     */
    public function getInnodbLogCompressedPages() {
        return $this->innodb_log_compressed_pages;
    }

    /**
     * @return null
     */
    public function getInnodbLogFileSize() {
        return $this->innodb_log_file_size;
    }

    /**
     * @return null
     */
    public function getInnodbLogFilesInGroup() {
        return $this->innodb_log_files_in_group;
    }

    /**
     * @return null
     */
    public function getInnodbLogGroupHomeDir() {
        return $this->innodb_log_group_home_dir;
    }

    /**
     * @return null
     */
    public function getInnodbLruScanDepth() {
        return $this->innodb_lru_scan_depth;
    }

    /**
     * @return null
     */
    public function getInnodbMaxDirtyPagesPct() {
        return $this->innodb_max_dirty_pages_pct;
    }

    /**
     * @return null
     */
    public function getInnodbMaxDirtyPagesPctLwm() {
        return $this->innodb_max_dirty_pages_pct_lwm;
    }

    /**
     * @return null
     */
    public function getInnodbMaxPurgeLag() {
        return $this->innodb_max_purge_lag;
    }

    /**
     * @return null
     */
    public function getInnodbMaxPurgeLagDelay() {
        return $this->innodb_max_purge_lag_delay;
    }

    /**
     * @return null
     */
    public function getInnodbMirroredLogGroups() {
        return $this->innodb_mirrored_log_groups;
    }

    /**
     * @return null
     */
    public function getInnodbMonitorDisable() {
        return $this->innodb_monitor_disable;
    }

    /**
     * @return null
     */
    public function getInnodbMonitorEnable() {
        return $this->innodb_monitor_enable;
    }

    /**
     * @return null
     */
    public function getInnodbMonitorReset() {
        return $this->innodb_monitor_reset;
    }

    /**
     * @return null
     */
    public function getInnodbMonitorResetAll() {
        return $this->innodb_monitor_reset_all;
    }

    /**
     * @return null
     */
    public function getInnodbOldBlocksPct() {
        return $this->innodb_old_blocks_pct;
    }

    /**
     * @return null
     */
    public function getInnodbOldBlocksTime() {
        return $this->innodb_old_blocks_time;
    }

    /**
     * @return null
     */
    public function getInnodbOnlineAlterLogMaxSize() {
        return $this->innodb_online_alter_log_max_size;
    }

    /**
     * @return null
     */
    public function getInnodbOpenFiles() {
        return $this->innodb_open_files;
    }

    /**
     * @return null
     */
    public function getInnodbOptimizeFulltextOnly() {
        return $this->innodb_optimize_fulltext_only;
    }

    /**
     * @return null
     */
    public function getInnodbPageSize() {
        return $this->innodb_page_size;
    }

    /**
     * @return null
     */
    public function getInnodbPrintAllDeadlocks() {
        return $this->innodb_print_all_deadlocks;
    }

    /**
     * @return null
     */
    public function getInnodbPurgeBatchSize() {
        return $this->innodb_purge_batch_size;
    }

    /**
     * @return null
     */
    public function getInnodbPurgeThreads() {
        return $this->innodb_purge_threads;
    }

    /**
     * @return null
     */
    public function getInnodbRandomReadAhead() {
        return $this->innodb_random_read_ahead;
    }

    /**
     * @return null
     */
    public function getInnodbReadAheadThreshold() {
        return $this->innodb_read_ahead_threshold;
    }

    /**
     * @return null
     */
    public function getInnodbReadIoThreads() {
        return $this->innodb_read_io_threads;
    }

    /**
     * @return null
     */
    public function getInnodbReadOnly() {
        return $this->innodb_read_only;
    }

    /**
     * @return null
     */
    public function getInnodbReplicationDelay() {
        return $this->innodb_replication_delay;
    }

    /**
     * @return null
     */
    public function getInnodbRollbackOnTimeout() {
        return $this->innodb_rollback_on_timeout;
    }

    /**
     * @return null
     */
    public function getInnodbRollbackSegments() {
        return $this->innodb_rollback_segments;
    }

    /**
     * @return null
     */
    public function getInnodbSortBufferSize() {
        return $this->innodb_sort_buffer_size;
    }

    /**
     * @return null
     */
    public function getInnodbSpinWaitDelay() {
        return $this->innodb_spin_wait_delay;
    }

    /**
     * @return null
     */
    public function getInnodbStatsAutoRecalc() {
        return $this->innodb_stats_auto_recalc;
    }

    /**
     * @return null
     */
    public function getInnodbStatsMethod() {
        return $this->innodb_stats_method;
    }

    /**
     * @return null
     */
    public function getInnodbStatsOnMetadata() {
        return $this->innodb_stats_on_metadata;
    }

    /**
     * @return null
     */
    public function getInnodbStatsPersistent() {
        return $this->innodb_stats_persistent;
    }

    /**
     * @return null
     */
    public function getInnodbStatsPersistentSamplePages() {
        return $this->innodb_stats_persistent_sample_pages;
    }

    /**
     * @return null
     */
    public function getInnodbStatsSamplePages() {
        return $this->innodb_stats_sample_pages;
    }

    /**
     * @return null
     */
    public function getInnodbStatsTransientSamplePages() {
        return $this->innodb_stats_transient_sample_pages;
    }

    /**
     * @return null
     */
    public function getInnodbStatusOutput() {
        return $this->innodb_status_output;
    }

    /**
     * @return null
     */
    public function getInnodbStatusOutputLocks() {
        return $this->innodb_status_output_locks;
    }

    /**
     * @return null
     */
    public function getInnodbStrictMode() {
        return $this->innodb_strict_mode;
    }

    /**
     * @return null
     */
    public function getInnodbSupportXa() {
        return $this->innodb_support_xa;
    }

    /**
     * @return null
     */
    public function getInnodbSyncArraySize() {
        return $this->innodb_sync_array_size;
    }

    /**
     * @return null
     */
    public function getInnodbSyncSpinLoops() {
        return $this->innodb_sync_spin_loops;
    }

    /**
     * @return null
     */
    public function getInnodbTableLocks() {
        return $this->innodb_table_locks;
    }

    /**
     * @return null
     */
    public function getInnodbThreadConcurrency() {
        return $this->innodb_thread_concurrency;
    }

    /**
     * @return null
     */
    public function getInnodbThreadSleepDelay() {
        return $this->innodb_thread_sleep_delay;
    }

    /**
     * @return null
     */
    public function getInnodbUndoDirectory() {
        return $this->innodb_undo_directory;
    }

    /**
     * @return null
     */
    public function getInnodbUndoLogs() {
        return $this->innodb_undo_logs;
    }

    /**
     * @return null
     */
    public function getInnodbUndoTablespaces() {
        return $this->innodb_undo_tablespaces;
    }

    /**
     * @return null
     */
    public function getInnodbUseNativeAio() {
        return $this->innodb_use_native_aio;
    }

    /**
     * @return null
     */
    public function getInnodbUseSysMalloc() {
        return $this->innodb_use_sys_malloc;
    }

    /**
     * @return null
     */
    public function getInnodbVersion() {
        return $this->innodb_version;
    }

    /**
     * @return null
     */
    public function getInnodbWriteIoThreads() {
        return $this->innodb_write_io_threads;
    }

    /**
     * @return null
     */
    public function getInsertId() {
        return $this->insert_id;
    }

    /**
     * @return null
     */
    public function getInteractiveTimeout() {
        return $this->interactive_timeout;
    }

    /**
     * @return null
     */
    public function getJoinBufferSize() {
        return $this->join_buffer_size;
    }

    /**
     * @return null
     */
    public function getKeepFilesOnCreate() {
        return $this->keep_files_on_create;
    }

    /**
     * @return null
     */
    public function getKeyBufferSize() {
        return $this->key_buffer_size;
    }

    /**
     * @return null
     */
    public function getKeyCacheAgeThreshold() {
        return $this->key_cache_age_threshold;
    }

    /**
     * @return null
     */
    public function getKeyCacheBlockSize() {
        return $this->key_cache_block_size;
    }

    /**
     * @return null
     */
    public function getKeyCacheDivisionLimit() {
        return $this->key_cache_division_limit;
    }

    /**
     * @return null
     */
    public function getLargeFilesSupport() {
        return $this->large_files_support;
    }

    /**
     * @return null
     */
    public function getLargePageSize() {
        return $this->large_page_size;
    }

    /**
     * @return null
     */
    public function getLargePages() {
        return $this->large_pages;
    }

    /**
     * @return null
     */
    public function getLastInsertId() {
        return $this->last_insert_id;
    }

    /**
     * @return null
     */
    public function getLcMessages() {
        return $this->lc_messages;
    }

    /**
     * @return null
     */
    public function getLcMessagesDir() {
        return $this->lc_messages_dir;
    }

    /**
     * @return null
     */
    public function getLcTimeNames() {
        return $this->lc_time_names;
    }

    /**
     * @return null
     */
    public function getLicense() {
        return $this->license;
    }

    /**
     * @return null
     */
    public function getLocalInfile() {
        return $this->local_infile;
    }

    /**
     * @return null
     */
    public function getLockWaitTimeout() {
        return $this->lock_wait_timeout;
    }

    /**
     * @return null
     */
    public function getLockedInMemory() {
        return $this->locked_in_memory;
    }

    /**
     * @return null
     */
    public function getLogBin() {
        return $this->log_bin;
    }

    /**
     * @return null
     */
    public function getLogBinBasename() {
        return $this->log_bin_basename;
    }

    /**
     * @return null
     */
    public function getLogBinIndex() {
        return $this->log_bin_index;
    }

    /**
     * @return null
     */
    public function getLogBinTrustFunctionCreators() {
        return $this->log_bin_trust_function_creators;
    }

    /**
     * @return null
     */
    public function getLogBinUseV1RowEvents() {
        return $this->log_bin_use_v1_row_events;
    }

    /**
     * @return null
     */
    public function getLogError() {
        return $this->log_error;
    }

    /**
     * @return null
     */
    public function getLogOutput() {
        return $this->log_output;
    }

    /**
     * @return null
     */
    public function getLogQueriesNotUsingIndexes() {
        return $this->log_queries_not_using_indexes;
    }

    /**
     * @return null
     */
    public function getLogSlaveUpdates() {
        return $this->log_slave_updates;
    }

    /**
     * @return null
     */
    public function getLogSlowAdminStatements() {
        return $this->log_slow_admin_statements;
    }

    /**
     * @return null
     */
    public function getLogSlowSlaveStatements() {
        return $this->log_slow_slave_statements;
    }

    /**
     * @return null
     */
    public function getLogThrottleQueriesNotUsingIndexes() {
        return $this->log_throttle_queries_not_using_indexes;
    }

    /**
     * @return null
     */
    public function getLogWarnings() {
        return $this->log_warnings;
    }

    /**
     * @return null
     */
    public function getLongQueryTime() {
        return $this->long_query_time;
    }

    /**
     * @return null
     */
    public function getLowPriorityUpdates() {
        return $this->low_priority_updates;
    }

    /**
     * @return null
     */
    public function getLowerCaseFileSystem() {
        return $this->lower_case_file_system;
    }

    /**
     * @return null
     */
    public function getLowerCaseTableNames() {
        return $this->lower_case_table_names;
    }

    /**
     * @return null
     */
    public function getMasterInfoRepository() {
        return $this->master_info_repository;
    }

    /**
     * @return null
     */
    public function getMasterVerifyChecksum() {
        return $this->master_verify_checksum;
    }

    /**
     * @return null
     */
    public function getMaxAllowedPacket() {
        return $this->max_allowed_packet;
    }

    /**
     * @return null
     */
    public function getMaxBinlogCacheSize() {
        return $this->max_binlog_cache_size;
    }

    /**
     * @return null
     */
    public function getMaxBinlogSize() {
        return $this->max_binlog_size;
    }

    /**
     * @return null
     */
    public function getMaxBinlogStmtCacheSize() {
        return $this->max_binlog_stmt_cache_size;
    }

    /**
     * @return null
     */
    public function getMaxConnectErrors() {
        return $this->max_connect_errors;
    }

    /**
     * @return null
     */
    public function getMaxConnections() {
        return $this->max_connections;
    }

    /**
     * @return null
     */
    public function getMaxDelayedThreads() {
        return $this->max_delayed_threads;
    }

    /**
     * @return null
     */
    public function getMaxErrorCount() {
        return $this->max_error_count;
    }

    /**
     * @return null
     */
    public function getMaxHeapTableSize() {
        return $this->max_heap_table_size;
    }

    /**
     * @return null
     */
    public function getMaxInsertDelayedThreads() {
        return $this->max_insert_delayed_threads;
    }

    /**
     * @return null
     */
    public function getMaxJoinSize() {
        return $this->max_join_size;
    }

    /**
     * @return null
     */
    public function getMaxLengthForSortData() {
        return $this->max_length_for_sort_data;
    }

    /**
     * @return null
     */
    public function getMaxPreparedStmtCount() {
        return $this->max_prepared_stmt_count;
    }

    /**
     * @return null
     */
    public function getMaxRelayLogSize() {
        return $this->max_relay_log_size;
    }

    /**
     * @return null
     */
    public function getMaxSeeksForKey() {
        return $this->max_seeks_for_key;
    }

    /**
     * @return null
     */
    public function getMaxSortLength() {
        return $this->max_sort_length;
    }

    /**
     * @return null
     */
    public function getMaxSpRecursionDepth() {
        return $this->max_sp_recursion_depth;
    }

    /**
     * @return null
     */
    public function getMaxTmpTables() {
        return $this->max_tmp_tables;
    }

    /**
     * @return null
     */
    public function getMaxUserConnections() {
        return $this->max_user_connections;
    }

    /**
     * @return null
     */
    public function getMaxWriteLockCount() {
        return $this->max_write_lock_count;
    }

    /**
     * @return null
     */
    public function getMetadataLocksCacheSize() {
        return $this->metadata_locks_cache_size;
    }

    /**
     * @return null
     */
    public function getMetadataLocksHashInstances() {
        return $this->metadata_locks_hash_instances;
    }

    /**
     * @return null
     */
    public function getMinExaminedRowLimit() {
        return $this->min_examined_row_limit;
    }

    /**
     * @return null
     */
    public function getMultiRangeCount() {
        return $this->multi_range_count;
    }

    /**
     * @return null
     */
    public function getMyisamDataPointerSize() {
        return $this->myisam_data_pointer_size;
    }

    /**
     * @return null
     */
    public function getMyisamMaxSortFileSize() {
        return $this->myisam_max_sort_file_size;
    }

    /**
     * @return null
     */
    public function getMyisamMmapSize() {
        return $this->myisam_mmap_size;
    }

    /**
     * @return null
     */
    public function getMyisamRecoverOptions() {
        return $this->myisam_recover_options;
    }

    /**
     * @return null
     */
    public function getMyisamRepairThreads() {
        return $this->myisam_repair_threads;
    }

    /**
     * @return null
     */
    public function getMyisamSortBufferSize() {
        return $this->myisam_sort_buffer_size;
    }

    /**
     * @return null
     */
    public function getMyisamStatsMethod() {
        return $this->myisam_stats_method;
    }

    /**
     * @return null
     */
    public function getMyisamUseMmap() {
        return $this->myisam_use_mmap;
    }

    /**
     * @return null
     */
    public function getNetBufferLength() {
        return $this->net_buffer_length;
    }

    /**
     * @return null
     */
    public function getNetReadTimeout() {
        return $this->net_read_timeout;
    }

    /**
     * @return null
     */
    public function getNetRetryCount() {
        return $this->net_retry_count;
    }

    /**
     * @return null
     */
    public function getNetWriteTimeout() {
        return $this->net_write_timeout;
    }

    /**
     * @return null
     */
    public function getNew() {
        return $this->new;
    }

    /**
     * @return null
     */
    public function getOld() {
        return $this->old;
    }

    /**
     * @return null
     */
    public function getOldAlterTable() {
        return $this->old_alter_table;
    }

    /**
     * @return null
     */
    public function getOldPasswords() {
        return $this->old_passwords;
    }

    /**
     * @return null
     */
    public function getOpenFilesLimit() {
        return $this->open_files_limit;
    }

    /**
     * @return null
     */
    public function getOptimizerPruneLevel() {
        return $this->optimizer_prune_level;
    }

    /**
     * @return null
     */
    public function getOptimizerSearchDepth() {
        return $this->optimizer_search_depth;
    }

    /**
     * @return null
     */
    public function getOptimizerSwitch() {
        return $this->optimizer_switch;
    }

    /**
     * @return null
     */
    public function getOptimizerTrace() {
        return $this->optimizer_trace;
    }

    /**
     * @return null
     */
    public function getOptimizerTraceFeatures() {
        return $this->optimizer_trace_features;
    }

    /**
     * @return null
     */
    public function getOptimizerTraceLimit() {
        return $this->optimizer_trace_limit;
    }

    /**
     * @return null
     */
    public function getOptimizerTraceMaxMemSize() {
        return $this->optimizer_trace_max_mem_size;
    }

    /**
     * @return null
     */
    public function getOptimizerTraceOffset() {
        return $this->optimizer_trace_offset;
    }

    /**
     * @return null
     */
    public function getPerformanceSchema() {
        return $this->performance_schema;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaAccountsSize() {
        return $this->performance_schema_accounts_size;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaDigestsSize() {
        return $this->performance_schema_digests_size;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaEventsStagesHistoryLongSize() {
        return $this->performance_schema_events_stages_history_long_size;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaEventsStagesHistorySize() {
        return $this->performance_schema_events_stages_history_size;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaEventsStatementsHistoryLongSize() {
        return $this->performance_schema_events_statements_history_long_size;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaEventsStatementsHistorySize() {
        return $this->performance_schema_events_statements_history_size;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaEventsWaitsHistoryLongSize() {
        return $this->performance_schema_events_waits_history_long_size;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaEventsWaitsHistorySize() {
        return $this->performance_schema_events_waits_history_size;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaHostsSize() {
        return $this->performance_schema_hosts_size;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaMaxCondClasses() {
        return $this->performance_schema_max_cond_classes;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaMaxCondInstances() {
        return $this->performance_schema_max_cond_instances;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaMaxFileClasses() {
        return $this->performance_schema_max_file_classes;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaMaxFileHandles() {
        return $this->performance_schema_max_file_handles;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaMaxFileInstances() {
        return $this->performance_schema_max_file_instances;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaMaxMutexClasses() {
        return $this->performance_schema_max_mutex_classes;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaMaxMutexInstances() {
        return $this->performance_schema_max_mutex_instances;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaMaxRwlockClasses() {
        return $this->performance_schema_max_rwlock_classes;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaMaxRwlockInstances() {
        return $this->performance_schema_max_rwlock_instances;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaMaxSocketClasses() {
        return $this->performance_schema_max_socket_classes;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaMaxSocketInstances() {
        return $this->performance_schema_max_socket_instances;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaMaxStageClasses() {
        return $this->performance_schema_max_stage_classes;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaMaxStatementClasses() {
        return $this->performance_schema_max_statement_classes;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaMaxTableHandles() {
        return $this->performance_schema_max_table_handles;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaMaxTableInstances() {
        return $this->performance_schema_max_table_instances;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaMaxThreadClasses() {
        return $this->performance_schema_max_thread_classes;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaMaxThreadInstances() {
        return $this->performance_schema_max_thread_instances;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaSessionConnectAttrsSize() {
        return $this->performance_schema_session_connect_attrs_size;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaSetupActorsSize() {
        return $this->performance_schema_setup_actors_size;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaSetupObjectsSize() {
        return $this->performance_schema_setup_objects_size;
    }

    /**
     * @return null
     */
    public function getPerformanceSchemaUsersSize() {
        return $this->performance_schema_users_size;
    }

    /**
     * @return null
     */
    public function getPidFile() {
        return $this->pid_file;
    }

    /**
     * @return null
     */
    public function getPluginDir() {
        return $this->plugin_dir;
    }

    /**
     * @return null
     */
    public function getPort() {
        return $this->port;
    }

    /**
     * @return null
     */
    public function getPreloadBufferSize() {
        return $this->preload_buffer_size;
    }

    /**
     * @return null
     */
    public function getProfiling() {
        return $this->profiling;
    }

    /**
     * @return null
     */
    public function getProfilingHistorySize() {
        return $this->profiling_history_size;
    }

    /**
     * @return null
     */
    public function getProtocolVersion() {
        return $this->protocol_version;
    }

    /**
     * @return null
     */
    public function getProxyUser() {
        return $this->proxy_user;
    }

    /**
     * @return null
     */
    public function getPseudoSlaveMode() {
        return $this->pseudo_slave_mode;
    }

    /**
     * @return null
     */
    public function getPseudoThreadId() {
        return $this->pseudo_thread_id;
    }

    /**
     * @return null
     */
    public function getQueryAllocBlockSize() {
        return $this->query_alloc_block_size;
    }

    /**
     * @return null
     */
    public function getQueryCacheLimit() {
        return $this->query_cache_limit;
    }

    /**
     * @return null
     */
    public function getQueryCacheMinResUnit() {
        return $this->query_cache_min_res_unit;
    }

    /**
     * @return null
     */
    public function getQueryCacheSize() {
        return $this->query_cache_size;
    }

    /**
     * @return null
     */
    public function getQueryCacheType() {
        return $this->query_cache_type;
    }

    /**
     * @return null
     */
    public function getQueryCacheWlockInvalidate() {
        return $this->query_cache_wlock_invalidate;
    }

    /**
     * @return null
     */
    public function getQueryPreallocSize() {
        return $this->query_prealloc_size;
    }

    /**
     * @return null
     */
    public function getRandSeed1() {
        return $this->rand_seed1;
    }

    /**
     * @return null
     */
    public function getRandSeed2() {
        return $this->rand_seed2;
    }

    /**
     * @return null
     */
    public function getRangeAllocBlockSize() {
        return $this->range_alloc_block_size;
    }

    /**
     * @return null
     */
    public function getReadBufferSize() {
        return $this->read_buffer_size;
    }

    /**
     * @return null
     */
    public function getReadOnly() {
        return $this->read_only;
    }

    /**
     * @return null
     */
    public function getReadRndBufferSize() {
        return $this->read_rnd_buffer_size;
    }

    /**
     * @return null
     */
    public function getRelayLog() {
        return $this->relay_log;
    }

    /**
     * @return null
     */
    public function getRelayLogBasename() {
        return $this->relay_log_basename;
    }

    /**
     * @return null
     */
    public function getRelayLogIndex() {
        return $this->relay_log_index;
    }

    /**
     * @return null
     */
    public function getRelayLogInfoFile() {
        return $this->relay_log_info_file;
    }

    /**
     * @return null
     */
    public function getRelayLogInfoRepository() {
        return $this->relay_log_info_repository;
    }

    /**
     * @return null
     */
    public function getRelayLogPurge() {
        return $this->relay_log_purge;
    }

    /**
     * @return null
     */
    public function getRelayLogRecovery() {
        return $this->relay_log_recovery;
    }

    /**
     * @return null
     */
    public function getRelayLogSpaceLimit() {
        return $this->relay_log_space_limit;
    }

    /**
     * @return null
     */
    public function getReportHost() {
        return $this->report_host;
    }

    /**
     * @return null
     */
    public function getReportPassword() {
        return $this->report_password;
    }

    /**
     * @return null
     */
    public function getReportPort() {
        return $this->report_port;
    }

    /**
     * @return null
     */
    public function getReportUser() {
        return $this->report_user;
    }

    /**
     * @return null
     */
    public function getRplStopSlaveTimeout() {
        return $this->rpl_stop_slave_timeout;
    }

    /**
     * @return null
     */
    public function getSecureAuth() {
        return $this->secure_auth;
    }

    /**
     * @return null
     */
    public function getSecureFilePriv() {
        return $this->secure_file_priv;
    }

    /**
     * @return null
     */
    public function getServerId() {
        return $this->server_id;
    }

    /**
     * @return null
     */
    public function getServerIdBits() {
        return $this->server_id_bits;
    }

    /**
     * @return null
     */
    public function getServerUuid() {
        return $this->server_uuid;
    }

    /**
     * @return null
     */
    public function getSkipExternalLocking() {
        return $this->skip_external_locking;
    }

    /**
     * @return null
     */
    public function getSkipNameResolve() {
        return $this->skip_name_resolve;
    }

    /**
     * @return null
     */
    public function getSkipNetworking() {
        return $this->skip_networking;
    }

    /**
     * @return null
     */
    public function getSkipShowDatabase() {
        return $this->skip_show_database;
    }

    /**
     * @return null
     */
    public function getSlaveAllowBatching() {
        return $this->slave_allow_batching;
    }

    /**
     * @return null
     */
    public function getSlaveCheckpointGroup() {
        return $this->slave_checkpoint_group;
    }

    /**
     * @return null
     */
    public function getSlaveCheckpointPeriod() {
        return $this->slave_checkpoint_period;
    }

    /**
     * @return null
     */
    public function getSlaveCompressedProtocol() {
        return $this->slave_compressed_protocol;
    }

    /**
     * @return null
     */
    public function getSlaveExecMode() {
        return $this->slave_exec_mode;
    }

    /**
     * @return null
     */
    public function getSlaveLoadTmpdir() {
        return $this->slave_load_tmpdir;
    }

    /**
     * @return null
     */
    public function getSlaveMaxAllowedPacket() {
        return $this->slave_max_allowed_packet;
    }

    /**
     * @return null
     */
    public function getSlaveNetTimeout() {
        return $this->slave_net_timeout;
    }

    /**
     * @return null
     */
    public function getSlaveParallelWorkers() {
        return $this->slave_parallel_workers;
    }

    /**
     * @return null
     */
    public function getSlavePendingJobsSizeMax() {
        return $this->slave_pending_jobs_size_max;
    }

    /**
     * @return null
     */
    public function getSlaveRowsSearchAlgorithms() {
        return $this->slave_rows_search_algorithms;
    }

    /**
     * @return null
     */
    public function getSlaveSkipErrors() {
        return $this->slave_skip_errors;
    }

    /**
     * @return null
     */
    public function getSlaveSqlVerifyChecksum() {
        return $this->slave_sql_verify_checksum;
    }

    /**
     * @return null
     */
    public function getSlaveTransactionRetries() {
        return $this->slave_transaction_retries;
    }

    /**
     * @return null
     */
    public function getSlaveTypeConversions() {
        return $this->slave_type_conversions;
    }

    /**
     * @return null
     */
    public function getSlowLaunchTime() {
        return $this->slow_launch_time;
    }

    /**
     * @return null
     */
    public function getSlowQueryLog() {
        return $this->slow_query_log;
    }

    /**
     * @return null
     */
    public function getSlowQueryLogFile() {
        return $this->slow_query_log_file;
    }

    /**
     * @return null
     */
    public function getSocket() {
        return $this->socket;
    }

    /**
     * @return null
     */
    public function getSortBufferSize() {
        return $this->sort_buffer_size;
    }

    /**
     * @return null
     */
    public function getSqlAutoIsNull() {
        return $this->sql_auto_is_null;
    }

    /**
     * @return null
     */
    public function getSqlBigSelects() {
        return $this->sql_big_selects;
    }

    /**
     * @return null
     */
    public function getSqlBufferResult() {
        return $this->sql_buffer_result;
    }

    /**
     * @return null
     */
    public function getSqlLogBin() {
        return $this->sql_log_bin;
    }

    /**
     * @return null
     */
    public function getSqlLogOff() {
        return $this->sql_log_off;
    }

    /**
     * @return null
     */
    public function getSqlMode() {
        return $this->sql_mode;
    }

    /**
     * @return null
     */
    public function getSqlNotes() {
        return $this->sql_notes;
    }

    /**
     * @return null
     */
    public function getSqlQuoteShowCreate() {
        return $this->sql_quote_show_create;
    }

    /**
     * @return null
     */
    public function getSqlSafeUpdates() {
        return $this->sql_safe_updates;
    }

    /**
     * @return null
     */
    public function getSqlSelectLimit() {
        return $this->sql_select_limit;
    }

    /**
     * @return null
     */
    public function getSqlSlaveSkipCounter() {
        return $this->sql_slave_skip_counter;
    }

    /**
     * @return null
     */
    public function getSqlWarnings() {
        return $this->sql_warnings;
    }

    /**
     * @return null
     */
    public function getSslCa() {
        return $this->ssl_ca;
    }

    /**
     * @return null
     */
    public function getSslCapath() {
        return $this->ssl_capath;
    }

    /**
     * @return null
     */
    public function getSslCert() {
        return $this->ssl_cert;
    }

    /**
     * @return null
     */
    public function getSslCipher() {
        return $this->ssl_cipher;
    }

    /**
     * @return null
     */
    public function getSslCrl() {
        return $this->ssl_crl;
    }

    /**
     * @return null
     */
    public function getSslCrlpath() {
        return $this->ssl_crlpath;
    }

    /**
     * @return null
     */
    public function getSslKey() {
        return $this->ssl_key;
    }

    /**
     * @return null
     */
    public function getStorageEngine() {
        return $this->storage_engine;
    }

    /**
     * @return null
     */
    public function getStoredProgramCache() {
        return $this->stored_program_cache;
    }

    /**
     * @return null
     */
    public function getSyncBinlog() {
        return $this->sync_binlog;
    }

    /**
     * @return null
     */
    public function getSyncFrm() {
        return $this->sync_frm;
    }

    /**
     * @return null
     */
    public function getSyncMasterInfo() {
        return $this->sync_master_info;
    }

    /**
     * @return null
     */
    public function getSyncRelayLog() {
        return $this->sync_relay_log;
    }

    /**
     * @return null
     */
    public function getSyncRelayLogInfo() {
        return $this->sync_relay_log_info;
    }

    /**
     * @return null
     */
    public function getSystemTimeZone() {
        return $this->system_time_zone;
    }

    /**
     * @return null
     */
    public function getTableDefinitionCache() {
        return $this->table_definition_cache;
    }

    /**
     * @return null
     */
    public function getTableOpenCache() {
        return $this->table_open_cache;
    }

    /**
     * @return null
     */
    public function getTableOpenCacheInstances() {
        return $this->table_open_cache_instances;
    }

    /**
     * @return null
     */
    public function getThreadCacheSize() {
        return $this->thread_cache_size;
    }

    /**
     * @return null
     */
    public function getThreadConcurrency() {
        return $this->thread_concurrency;
    }

    /**
     * @return null
     */
    public function getThreadHandling() {
        return $this->thread_handling;
    }

    /**
     * @return null
     */
    public function getThreadStack() {
        return $this->thread_stack;
    }

    /**
     * @return null
     */
    public function getTimeFormat() {
        return $this->time_format;
    }

    /**
     * @return null
     */
    public function getTimeZone() {
        return $this->time_zone;
    }

    /**
     * @return null
     */
    public function getTimedMutexes() {
        return $this->timed_mutexes;
    }

    /**
     * @return null
     */
    public function getTimestamp() {
        return $this->timestamp;
    }

    /**
     * @return null
     */
    public function getTmpTableSize() {
        return $this->tmp_table_size;
    }

    /**
     * @return null
     */
    public function getTmpdir() {
        return $this->tmpdir;
    }

    /**
     * @return null
     */
    public function getTransactionAllocBlockSize() {
        return $this->transaction_alloc_block_size;
    }

    /**
     * @return null
     */
    public function getTransactionAllowBatching() {
        return $this->transaction_allow_batching;
    }

    /**
     * @return null
     */
    public function getTransactionPreallocSize() {
        return $this->transaction_prealloc_size;
    }

    /**
     * @return null
     */
    public function getTxIsolation() {
        return $this->tx_isolation;
    }

    /**
     * @return null
     */
    public function getTxReadOnly() {
        return $this->tx_read_only;
    }

    /**
     * @return null
     */
    public function getUniqueChecks() {
        return $this->unique_checks;
    }

    /**
     * @return null
     */
    public function getUpdatableViewsWithLimit() {
        return $this->updatable_views_with_limit;
    }

    /**
     * @return null
     */
    public function getVersion() {
        return $this->version;
    }

    /**
     * @return null
     */
    public function getVersionComment() {
        return $this->version_comment;
    }

    /**
     * @return null
     */
    public function getVersionCompileMachine() {
        return $this->version_compile_machine;
    }

    /**
     * @return null
     */
    public function getVersionCompileOs() {
        return $this->version_compile_os;
    }

    /**
     * @return null
     */
    public function getWaitTimeout() {
        return $this->wait_timeout;
    }

    /**
     * @return null
     */
    public function getWarningCount() {
        return $this->warning_count;
    }

}