<?php

namespace Utils\Files;

use Model\Conversion\UploadElement;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Exception as WriterException;

class CSV
{
    /**
     * @param UploadElement $file
     * @param string $prefix
     *
     * @return false|string
     * @throws Exception
     * @throws WriterException
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
     * @return list<string|null>|null
     */
    public static function headers(string $filepath): ?array
    {
        $lines = file($filepath, FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return null;
        }

        $csv = array_map("str_getcsv", $lines);

        return array_shift($csv);
    }

    /**
     * @return list<list<string|null>>
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
     * @param list<list<string>> $data
     */
    public static function save(string $filepath, array $data = []): bool
    {
        File::create($filepath);

        $fp = fopen($filepath, 'w');
        if ($fp === false) {
            return false;
        }
        foreach ($data as $fields) {
            if (!fputcsv($fp, $fields)) {
                fclose($fp);
                return false;
            }
        }
        fclose($fp);

        return true;
    }
}