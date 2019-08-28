<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 11/09/2018
 * Time: 18:00
 */

route( '/api/v3/jobs/[:id_job]/[:password]', 'GET', '\API\V3\ChunkController', 'show' );
route( '/api/v3/jobs/[:id_job]/[:password]/quality-report/segments', 'GET',
        'Features\ReviewExtended\Controller\API\QualityReportController', 'segments' );

route( '/api/v3/word-count/raw', 'POST', '\API\V3\CountWordController', 'rawWords' );