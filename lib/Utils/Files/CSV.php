<?php

namespace Files;

class CSV {

    /**
     * @param $filepath
     *
     * @return mixed
     */
    public static function headers($filepath)
    {
        $csv = array_map("str_getcsv", file($filepath,FILE_SKIP_EMPTY_LINES));

        return array_shift($csv);
    }

    /**
     * @param string $filepath
     * @param string $delimiter
     *
     * @return array
     */
    public static function parse( $filepath, $delimiter = ',' ) {

        $output = [];

        if ( ( $handle = fopen( $filepath, "r" ) ) !== false ) {
            while ( ( $data = fgetcsv( $handle, 1000, $delimiter ) ) !== false ) {
                $output[] = $data;
            }
            fclose( $handle );
        }

        return $output;
    }

    /**
     * @param string $filepath
     * @param array  $data
     *
     * @return bool
     */
    public static function save( $filepath, array $data = [] ) {
        File::create($filepath);

        $fp = fopen( $filepath, 'w' );
        foreach ( $data as $fields ) {
            if ( !fputcsv( $fp, $fields ) ) {
                return false;
            }
        }
        fclose( $fp );

        return true;
    }
}