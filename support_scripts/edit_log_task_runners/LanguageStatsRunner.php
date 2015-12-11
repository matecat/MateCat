<?php
$root = realpath( dirname( __FILE__ ) . '/../../' );
include_once $root . "/inc/Bootstrap.php";
Bootstrap::start();
require_once INIT::$MODEL_ROOT . '/queries.php';

use Analysis\Commons\AbstractDaemon;

/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 21/09/15
 * Time: 16.06
 */
class LanguageStatsRunner extends AbstractDaemon {


    public function __construct() {
        parent::__construct();
        Log::$fileName   = "languageStats.log";
        self::$sleeptime = 10; //60 * 60 * 24 * 30 * 1;
    }

    function main( $args ) {
        $db = Database::obtain();

        do {
            //TODO: create DAO for this
            $today     = date( "Y-m-d" );
            $queryJobs = "SELECT
                        source,
                        target,
                        sum( total_time_to_edit ) as total_time_to_edit,
                        sum( total_raw_wc ) as total_words,
                        sum( COALESCE (avg_post_editing_effort, 0) / coalesce(total_raw_wc, 1) ) as total_post_editing_effort,
                        count(*) as job_count
                      FROM
                        jobs j
                      WHERE
                        completed = 1
                        AND source = '%s'
                      GROUP BY target";

            $queryInsert = "INSERT into language_stats
                        (date, source, target, total_word_count, total_post_editing_effort, total_time_to_edit, job_count)
                        VALUES %s
                        ON DUPLICATE KEY UPDATE
                          total_post_editing_effort = values( total_post_editing_effort ),
                          total_time_to_edit = values( total_time_to_edit ),
                          job_count = values( job_count ),
                          total_word_count = values(total_word_count)";

            $updateTuplesTemplate = "( '%s', '%s', '%s', %f, %f, %f, %u )";

            $langsObj = Langs_Languages::getInstance();

            //getlanguage list
            $languages = $langsObj->getEnabledLanguages();
            $languages = Utils::array_column( $languages, 'code' );

            foreach ( $languages as $source_language ) {
                Log::doLog( "Current source_language: $source_language" );
                echo "Current source_language: $source_language\n";

                $languageStats = $db->fetch_array(
                        sprintf(
                                $queryJobs,
                                $source_language
                        )
                );

                $languageTuples = array();

                foreach ( $languageStats as $languageCoupleStat ) {
                    Log::doLog( "Current language couple: " . $source_language . "-" . $languageCoupleStat[ 'target' ] );
                    echo "Current language couple: " . $source_language . "-" . $languageCoupleStat[ 'target' ] . "\n";

                    $languageTuples[] = sprintf(
                            $updateTuplesTemplate,
                            $today,
                            $languageCoupleStat[ 'source' ],
                            $languageCoupleStat[ 'target' ],
                            round( $languageCoupleStat[ 'total_words' ], 4 ),
                            round( $languageCoupleStat[ 'total_post_editing_effort' ], 4 ),
                            round( $languageCoupleStat[ 'total_time_to_edit' ], 4 ),
                            $languageCoupleStat[ 'job_count' ]
                    );
                }

                if ( count( $languageTuples ) > 0 ) {

                    Log::doLog( "Found some stats. Saving in DB.." );
                    echo "Found some stats. Saving in DB..\n";
                    $db->query(
                            sprintf(
                                    $queryInsert,
                                    implode( ", ", $languageTuples )
                            )
                    );
                }

                usleep( 100 );
            }

            //for the moment, this daemon is single-loop-execution
            self::$RUNNING = false;

            if ( self::$RUNNING ) {
                sleep( self::$sleeptime );
            }

        } while ( self::$RUNNING );
    }

    public static function cleanShutDown() {
        // TODO: Implement cleanShutDown() method.
    }

}

$lsr = LanguageStatsRunner::getInstance();

/**
 * @var $lsr LanguageStatsRunner
 */
$lsr->main( null );