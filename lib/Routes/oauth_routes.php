<?php

route(
    '/webhooks/oauth/response/[:provider]', 'GET',
    [ 'Controller\Views\OauthResponseHandlerController', 'response']
);