<?php

route(
    '/webhooks/oauth/response/[:provider]', 'GET',
    [ '\oauthResponseHandlerController', 'response']
);