<?php

$matecatHome = getenv("MATECAT_HOME");
if ($matecatHome === false) {
    fwrite(STDERR, "MATECAT_HOME environment variable is not set\n");
    exit(1);
}

include_once realpath($matecatHome) . "/lib/Bootstrap.php";
Bootstrap::start();

use Utils\AsyncTasks\Workers\Analysis\FastAnalysis;
use Utils\Registry\AppConfig;

if ( AppConfig::$FAST_ANALYSIS_MEMORY_LIMIT != null ) {
    ini_set( "memory_limit", AppConfig::$FAST_ANALYSIS_MEMORY_LIMIT );
}


// Composition root: read the DB handle once from Bootstrap and inject it into
// the daemon, which threads it down to the DAOs it builds.
$daemon = FastAnalysis::getInstance(@$argv[1]);
$daemon->setDatabase(Bootstrap::getDatabase());

// Top-level safety net: main() guards each project individually, but a systemic fault (broken
// FeatureSet build, unrecoverable broker/DB state) can still escape the loop. Log it at error
// level and exit non-zero so the process supervisor restarts the daemon instead of it dying mute.
try {
    $daemon->main();
} catch (Throwable $e) {
    error_log("[FastAnalysis] fatal, daemon exiting: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    exit(1);
}
