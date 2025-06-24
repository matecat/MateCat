<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 10:11
 */

route(
        '/webhooks/gdrive/open', 'GET',
        [ 'Controller\ConnectedServices\GDrive\GDriveController', 'open' ]
);

route( '/gdrive/oauth/response', 'GET',
        [ 'Controller\ConnectedServices\GDrive\OAuthController', 'response' ]
);

route(
        '/gdrive/list', 'GET',
        [ 'Controller\ConnectedServices\GDrive\GDriveController', 'listImportedFiles' ]
);
route(
        '/gdrive/change', 'GET',
        [ 'Controller\ConnectedServices\GDrive\GDriveController', 'changeConversionParameters' ]
);
route(
        '/gdrive/delete/[:fileId]', 'GET',
        [ 'Controller\ConnectedServices\GDrive\GDriveController', 'deleteImportedFile' ]
);
