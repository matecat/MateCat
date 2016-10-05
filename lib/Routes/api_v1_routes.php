<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 10:05
 */


route( '/api/v1/jobs/[:id_job]/[:password]/stats', 'GET',  'API\V1\StatsController', 'stats' );
