<?php
$root = realpath( dirname( __FILE__ ) . '/../../' );
include_once $root . "/inc/Bootstrap.php";
Bootstrap::start();

use TaskRunner\Commons\AbstractDaemon;

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
        self::$sleepTime = 10; //60 * 60 * 24 * 30 * 1;
    }

    function main( $args = null ) {

        $lsDao = new LanguageStats_LanguageStatsDAO( Database::obtain() );

        do {

            $firstDayOfLastMonth = date( 'Y-m-d', strtotime( 'first day of last month' ) );

            $langsObj = Langs_Languages::getInstance();

            //get language list
            $languages = $langsObj->getEnabledLanguages();
            $languages = Utils::array_column( $languages, 'code' );

            $jobStatsDao = new Jobs_JobStatsDao( Database::obtain() );

            foreach ( $languages as $source_language ) {
                Log::doJsonLog( "Current source_language: $source_language" );
                echo "Current source_language: $source_language\n";

                $languageStats = $jobStatsDao->readBySource( $source_language );

                foreach ( $languageStats as $position => $languageCoupleStat ) {

                    if ( !self::isLanguageStatValid( $languageCoupleStat ) ) {
                        unset( $languageStats[ $position ] );
                        continue;
                    }

                    Log::doJsonLog( "Current language couple: " . $source_language . "-" . $languageCoupleStat->target . "(" . $languageCoupleStat->fuzzy_band . ")" );
                    echo "Current language couple: " . $source_language . "-" . $languageCoupleStat->target . "(" . $languageCoupleStat->fuzzy_band . ")\n";

                    $languageCoupleStat->date                      = $firstDayOfLastMonth;
                    $languageCoupleStat->total_word_count          = round( $languageCoupleStat->total_word_count, 4 );
                    $languageCoupleStat->total_post_editing_effort = round( $languageCoupleStat->total_post_editing_effort, 4 );
                    $languageCoupleStat->total_time_to_edit        = round( $languageCoupleStat->total_time_to_edit, 4 );

                }

                //if there is some data for this language couple, insert it
                if ( count( $languageStats ) > 0 ) {

                    Log::doJsonLog( "Found some stats. Saving in DB.." );
                    echo "Found some stats. Saving in DB..\n";

                    $result = $lsDao->createList( $languageStats );
                    if ( is_null( $result ) ) {
                        echo "ERROR: DAO failed to insert rows";
                    }

                }

                usleep( 100 );

            }

            Log::doJsonLog( "Everything completed. I can die." );
            echo "Everything completed. I can die.\n";

            //for the moment, this daemon is single-loop-execution
            $this->RUNNING = false;

            if ( $this->RUNNING ) {
                sleep( self::$sleepTime );
            }

        } while ( $this->RUNNING );
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

    private static function isLanguageStatValid( $languageStat ) {
        return (
                $languageStat->source != $languageStat->target &&
                preg_match( "#[a-z]{2,3}-[a-zA-Z-]{2,}#", $languageStat->source ) &&
                preg_match( "#[a-z]{2,3}-[a-zA-Z-]{2,}#", $languageStat->target )
        );
    }
}

$lsr = LanguageStatsRunner::getInstance();

/**
 * @var $lsr LanguageStatsRunner
 */
$lsr->main( null );
