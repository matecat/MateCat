<?php
/**
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 27/02/26
 * Time: 10:00
 */

namespace Model\Conversion\Adapter;

/**
 * Thin abstraction over the vendor static class
 * {@see \Matecat\XliffParser\XliffUtils\XliffProprietaryDetect},
 * allowing instance-based injection in {@see \Model\Conversion\ConversionHandler}
 * so that file-format detection can be mocked in unit tests.
 */
interface XliffDetectAdapterInterface
{
    /**
     * Determines whether the given file must be converted to XLIFF via Filters.
     *
     * @param string $filePath        Absolute path to the file.
     * @param bool   $enforceXliff    If true, even standard XLIFF files trigger conversion.
     * @param string $filtersAddress  The Filters service base URL.
     *
     * @return bool|int True if conversion is needed, false if not, or an integer error code.
     */
    public function fileMustBeConverted(string $filePath, bool $enforceXliff, string $filtersAddress): bool|int;

    /**
     * Returns proprietary-format metadata for the given file.
     *
     * @param string $filePath Absolute path to the file.
     *
     * @return array An associative array with at least a 'proprietary_name' key.
     */
    public function getInfo(string $filePath): array;
}

