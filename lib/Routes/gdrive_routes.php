<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 10:11
 */

route(
    '/webhooks/gdrive/open',
    'POST',
    ['Controller\API\GDrive\GDriveController', 'open']
);

route(
    '/gdrive/oauth/response',
    'GET',
    ['Controller\API\GDrive\OAuthController', 'response']
);

route(
    '/gdrive/list',
    'GET',
    ['Controller\API\GDrive\GDriveController', 'listImportedFiles']
);
route(
    '/gdrive/change',
    'GET',
    ['Controller\API\GDrive\GDriveController', 'changeConversionParameters']
);
route(
    '/gdrive/delete/[:fileId]',
    'GET',
    ['Controller\API\GDrive\GDriveController', 'deleteImportedFile']
);
