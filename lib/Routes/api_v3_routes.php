<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 11/09/2018
 * Time: 18:00
 */

$klein->with( '/api/v3/jobs/[:id_job]/[:password]/quality-report', function () {
    route( '/', 'GET', 'Features\ReviewImproved\Controller\API\QualityReportController', 'general' );
    route( '/segments', 'GET', 'Features\ReviewImproved\Controller\API\QualityReportController', 'segments' );
} );