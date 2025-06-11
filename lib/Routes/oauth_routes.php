<?php

route(
    '/webhooks/oauth/response/[:provider]', 'GET',
    [ 'Views\OauthResponseHandlerController', 'response']
);