<?php

route('/microsoft/oauth/response', 'GET',
    'ConnectedServices\Microsoft\MicrosoftController', 'response'
);