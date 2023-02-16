<?php

if (!function_exists('curl_file_create')) {
    function curl_file_create($filename, $mimetype = '', $postname = '') {
        return "@$filename;filename="
                . ($postname ?: basename($filename))
                . ($mimetype ? ";type=$mimetype" : '');
    }
}

