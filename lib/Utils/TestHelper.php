<?php

class TestHelper {
    public static function parseConfigFile( $env ) {
        return parse_ini_file(PROJECT_ROOT . '/inc/config.' . $env . '.ini', true);
    }
}

