<?php

define( 'PROJECT_ROOT', realpath( dirname( __FILE__ ) ) . DIRECTORY_SEPARATOR . '../../' );
require( PROJECT_ROOT . 'inc/Bootstrap.php' );

Bootstrap::start();

use CommandLineTasks\CopyFilesFromS3Task;
use CommandLineTasks\CreateTeamMembershipTask;
use CommandLineTasks\CreateTeamTask;
use CommandLineTasks\DumpSchemaTask;
use CommandLineTasks\FindElementInS3CacheTask;
use CommandLineTasks\Outsource\AirbnbOutsourceToHTS;
use CommandLineTasks\Outsource\MicrosoftOutsourceToHTS;
use CommandLineTasks\OwnerFeatures\AssignFeatureTask;
use CommandLineTasks\SecondPassReview\FixChunkReviewRecordCounts;
use CommandLineTasks\Test\PrepareDatabaseTask;
use Features\Dqf\Task\DqfAttributesDumpTask;
use Symfony\Component\Console\Application;

$app = new Application( "Tasks for instantquote", "1.0" );

$app->add( new CreateTeamTask() );
$app->add( new CreateTeamMembershipTask() );
$app->add( new AssignFeatureTask() );
$app->add( new PrepareDatabaseTask() );
$app->add( new DumpSchemaTask() );
$app->add( new DqfAttributesDumpTask() );
$app->add( new MicrosoftOutsourceToHTS() );
$app->add( new AirbnbOutsourceToHTS() );
$app->add( new CopyFilesFromS3Task() );
$app->add( new FindElementInS3CacheTask() );
$app->add( new FixChunkReviewRecordCounts() );

$app->run();
