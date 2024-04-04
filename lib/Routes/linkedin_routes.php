<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 09/09/16
 * Time: 10:11
 */

route('/linkedin/oauth/response', 'GET',
    'ConnectedServices\LinkedIn\LinkedInController', 'response'
);
