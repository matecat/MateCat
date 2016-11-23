<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 10:11
 */

route(
    '/webhooks/gdrive/open', 'GET',
    'ConnectedServices\GDrive\GDriveController', 'open'
);

route('/gdrive/oauth/response', 'GET',
    'ConnectedServices\GDrive\OAuthController', 'response'
);

route(
    '/gdrive/list', 'GET',
    'ConnectedServices\GDrive\GDriveController', 'listImportedFiles'
);
route(
    '/gdrive/change/[:sourceLanguage]', 'GET',
    'ConnectedServices\GDrive\GDriveController', 'changeSourceLanguage'
);
route(
    '/gdrive/delete/[:fileId]', 'GET',
    'ConnectedServices\GDrive\GDriveController', 'deleteImportedFile'
);
