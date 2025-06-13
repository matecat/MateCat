<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 16/05/25
 * Time: 19:29
 *
 */

global $klein;

// Page Views (replacing old viewController)
route( '/', 'GET', [ 'Views\UploadPageController', 'renderView' ] );
route( '/signin', 'GET', [ 'Views\SignInController', 'renderView' ] );
route( '/manage', 'GET', [ 'Views\ManageController', 'renderView' ] );
route( '/analyze/[:project_name]/[i:pid]-[:password]', 'GET', [ 'Views\AnalyzeController', 'renderView' ] );
route( '/jobanalysis/[i:pid]-[i:jid]-[:password]', 'GET', [ 'Views\AnalyzeController', 'renderView' ] );
route( '/revise-summary/[i:jid]-[:password]', 'GET', [ 'Views\QualityReportController', 'renderView' ] );
route( '/activityLog/[i:id_project]/[:password]', 'GET', [ 'Views\ActivityLogController', 'renderView' ] );
route( '/utils/xliff-to-target', 'GET', [ 'Views\XliffToTargetController', 'renderView' ] );

// outsource authentication callbacks
route( '/webhooks/outsource/success', 'GET', [ 'Views\OutsourceTo\TranslatedCallbackController', 'renderView' ] );
route( '/webhooks/outsource/failure', 'GET', [ 'Views\OutsourceTo\TranslatedCallbackController', 'renderView' ] );