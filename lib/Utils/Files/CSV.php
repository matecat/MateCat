<?php

namespace Utils\Files;

use Model\Conversion\UploadElement;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;
use PhpOffice\PhpSpreadsheet\Writer\Csv as CsvWriter;
use PhpOffice\PhpSpreadsheet\Writer\Exception as WriterException;
use RuntimeException;

class CSV
{
    /**
     * @param UploadElement $file
     * @param string $prefix
     *
     * @return false|string
     * @throws Exception
     * @throws WriterException
     * @throws RuntimeException
     */
    public static function extract(UploadElement $file, string $prefix = ''): false|string
    {
        if (!isset($file->file_path)) {
            return false;
        }

        $tmpFileName = tempnam("/tmp", $prefix);
        if ($tmpFileName === false) {
            throw new RuntimeException('Failed to create temporary file');
        }

        $reader = IOFactory::createReaderForFile($file->file_path);
        // An empty cell writes an empty CSV field either way; skipping it only saves load time.
        // Do not set readDataOnly: the CSV writer needs the number formats to print dates and numbers as shown.
        $reader->setReadEmptyCells(false);

        $spreadsheet = $reader->load($file->file_path);
        try {
            (new CsvWriter($spreadsheet))->save($tmpFileName);
        } finally {
            // Worksheets and the workbook reference each other, so the garbage collector never frees
            // them on its own: a large glossary would otherwise keep close to 1 GB alive for the rest
            // of the request.
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }

        $oldPath = $file->file_path;
        $file->file_path = $tmpFileName;

        unlink($oldPath);

        return $file->file_path;
    }

    /**
     * Reads only the first record, so the cost does not grow with the file.
     *
     * @return list<string|null>|null
     */
    public static function headers(string $filepath): ?array
    {
        if (!is_file($filepath) || !is_readable($filepath)) {
            return null;
        }

        $handle = fopen($filepath, 'r');
        if ($handle === false) {
            return null;
        }

        try {
            $headers = fgetcsv($handle, null, ',', '"', '\\');
        } finally {
            fclose($handle);
        }

        return $headers === false ? null : $headers;
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