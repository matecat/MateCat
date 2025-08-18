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
route( '/', 'GET', [ 'Controller\Views\UploadPageController', 'renderView' ] );
route( '/signin', 'GET', [ 'Controller\Views\SignInController', 'renderView' ] );
route( '/manage', 'GET', [ 'Controller\Views\ManageController', 'renderView' ] );
route( '/analyze/[:project_name]/[i:pid]-[:password]', 'GET', [ 'Controller\Views\AnalyzeController', 'renderView' ] );
route( '/jobanalysis/[i:pid]-[i:jid]-[:password]', 'GET', [ 'Controller\Views\AnalyzeController', 'renderView' ] );
route( '/revise-summary/[i:jid]-[:password]', 'GET', [ 'Controller\Views\QualityReportController', 'renderView' ] );
route( '/activityLog/[i:id_project]/[:password]', 'GET', [ 'Controller\Views\ActivityLogController', 'renderView' ] );
route( '/utils/xliff-to-target', 'GET', [ 'Controller\Views\XliffToTargetController', 'renderView' ] );

route( '/translate/[:project_name]/[:lang_pair]/[i:jid]-?[i:split]?-[:password]', 'GET', [ 'Controller\Views\CattoolController', 'renderView' ] );
route( '/revise/[:project_name]/[:lang_pair]/[i:jid]-?[i:split]?-[:password]', 'GET', [ 'Controller\Views\CattoolController', 'renderView' ] );
route( '/revise2/[:project_name]/[:lang_pair]/[i:jid]-?[i:split]?-[:password]', 'GET', [ 'Controller\Views\CattoolController', 'renderView' ] );

// outsource authentication callbacks
route( '/webhooks/outsource/success', 'GET', [ 'Controller\Views\OutsourceTo\TranslatedCallbackController', 'renderView' ] );
route( '/webhooks/outsource/failure', 'GET', [ 'Controller\Views\OutsourceTo\TranslatedCallbackController', 'renderView' ] );