<?php

namespace Utils\Files;

use Model\Conversion\UploadElement;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;

class CSV
{
    /**
     * @param UploadElement $file
     * @param string $prefix
     *
     * @return false|string
     * @throws Exception
     */
    public static function extract(UploadElement $file, string $prefix = ''): false|string
    {
        if (!isset($file->file_path)) {
            return false;
        }

        $tmpFileName = tempnam("/tmp", $prefix);

        $objReader = IOFactory::createReaderForFile($file->file_path);

        $objPHPExcel = $objReader->load($file->file_path);
        $objWriter = new \PhpOffice\PhpSpreadsheet\Writer\Csv($objPHPExcel);
        $objWriter->save($tmpFileName);

        $oldPath = $file->file_path;
        $file->file_path = $tmpFileName;

        unlink($oldPath);

        return $file->file_path;
    }

    /**
     * @param $filepath
     *
     * @return array
     */
    public static function headers($filepath): array
    {
        $csv = array_map("str_getcsv", file($filepath, FILE_SKIP_EMPTY_LINES));

        return array_shift($csv);
    }

    /**
     * @param string $filepath
     * @param string $delimiter
     *
     * @return array
     */
    public static function parseToArray(string $filepath, string $delimiter = ','): array
    {
        $output = [];

        if (($handle = fopen($filepath, "r")) !== false) {
            while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
                $output[] = $data;
            }
            fclose($handle);
        }

        return $output;
    }

    /**
     * @param string $filepath
     * @param array $data
     *
     * @return bool
     */
    public static function save(string $filepath, array $data = []): bool
    {
        File::create($filepath);

        $fp = fopen($filepath, 'w');
        foreach ($data as $fields) {
            if (!fputcsv($fp, $fields)) {
                return false;
            }
        }
        fclose($fp);

        return true;
    }
}