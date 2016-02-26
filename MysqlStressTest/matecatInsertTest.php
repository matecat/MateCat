<?php
/**
 * Created by PhpStorm.
 * User: roberto
 * Date: 12/02/16
 * Time: 11.18
 *
 * for i in {1..70}; do  nohup php ../matecat/cattool/matecatStressTest.php > stressTest{$i}.log &  done
 *
 */

$user = "matecat";
$pass = "matecat01";
$host = "52.58.33.27";
$db   = "matecat_sandbox";


//$user = "matecat_user";
//$pass = "matecat_user";
//$host = "localhost";
//$db   = "matecat";

while ( true ) {

    $con  = new PDO( "mysql:host=" . $host . ";dbname=" . $db, $user, $pass );

    try {
        $idJob = $con->query( 'select max(id_job) from segment_translations' )->fetchAll( PDO::FETCH_NUM )[0][0];
    } catch( Exception $e ){
        logMsg( $e-> getMessage() );
        logMsg( $e-> getTraceAsString() );
        exit (1);
    }

    $insertSegmentTranslations = '
            INSERT INTO segment_translations (
              `id_segment`,
              `id_job`,
              `segment_hash`,
              `autopropagated_from`,
              `status`,
              `translation`,
              `translation_date`,
              `time_to_edit`,
              `match_type`,
              `context_hash`,
              `eq_word_count`,
              `standard_word_count`,
              `suggestions_array`,
              `suggestion`,
              `suggestion_match`,
              `suggestion_source`,
              `suggestion_position`,
              `mt_qe`,
              `tm_analysis_status`,
              `locked`,
              `warning`,
              `serialized_errors_list`
            )
            SELECT
              `id_segment`,
              %u,
              `segment_hash`,
              `autopropagated_from`,
              `status`,
              `translation`,
              `translation_date`,
              `time_to_edit`,
              `match_type`,
              `context_hash`,
              `eq_word_count`,
              `standard_word_count`,
              `suggestions_array`,
              `suggestion`,
              `suggestion_match`,
              `suggestion_source`,
              `suggestion_position`,
              `mt_qe`,
              `tm_analysis_status`,
              `locked`,
              `warning`,
              `serialized_errors_list`
            FROM `segment_translations`
--            WHERE `id_job` = 42806;
            WHERE `id_job` = 274023;
    ';

    $query = sprintf( $insertSegmentTranslations, $idJob +1 );
    $rows = $con->exec( $query );

    logMsg( "Done ID Job " . ( $idJob +1 ) . " . Sleep 10 secs.\n" );
    sleep(10);

}

function logMsg( $msg ) {
    $now            = date( 'Y-m-d H:i:s' );
    $stringDataInfo = "[$now] ";

    echo $stringDataInfo . $msg . "\n";
}