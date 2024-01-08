<?php

namespace Files;

use PhpOffice\PhpSpreadsheet\IOFactory;
use stdClass;

class CSV
{
    /**
     * @param stdClass $file
     * @param string $prefix
     * @return false|string
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public static function extract($file, $prefix = '')
    {
        if(!isset($file->file_path)){
            return false;
        }

        $tmpFileName = tempnam( "/tmp", $prefix );

        $objReader = IOFactory::createReaderForFile( $file->file_path );

        $objPHPExcel = $objReader->load( $file->file_path );
        $objWriter   = new \PhpOffice\PhpSpreadsheet\Writer\Csv( $objPHPExcel );
        $objWriter->save( $tmpFileName );

        $oldPath             = $file->file_path;
        $file->file_path     = $tmpFileName;

        unlink( $oldPath );

        return $file->file_path;
    }

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
    public static function parseToArray($filepath, $delimiter = ',' ) {

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
     * @param $filepath
     * @param string $delimiter
     * @return string|null
     */
    public static function withoutHeaders($filepath, $delimiter = ',')
    {
        $csv = self::parseToArray($filepath);

        if(!is_array($csv)){
            return null;
        }

        unset($csv[0]);

        $out = "";

        foreach($csv as $arr) {
            $out .= implode($delimiter, $arr) . PHP_EOL;
        }

        return $out;
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