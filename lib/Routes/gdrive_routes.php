<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 10:11
 */

route(
    '/webhooks/gdrive/open', 'GET',
    'GDriveController', 'open'
);
route(
    '/gdrive/list', 'GET',
    'GDriveController', 'listImportedFiles'
);
route(
    '/gdrive/change/[:sourceLanguage]', 'GET',
    'GDriveController', 'changeSourceLanguage'
);
route(
    '/gdrive/delete/[:fileId]', 'GET',
    'GDriveController', 'deleteImportedFile'
);
route(
    '/gdrive/verify', 'GET',
    'GDriveController', 'isGDriveAccessible'
);