<?php
/**
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 27/02/26
 * Time: 10:00
 */

namespace Model\Conversion\Adapter;

use Exception;
use Model\Filters\DTO\IDto;

/**
 * Thin abstraction over the static {@see \Model\Conversion\Filters} class,
 * allowing instance-based injection in {@see \Model\Conversion\ConversionHandler}
 * so that HTTP/cURL and MySQL calls can be mocked in unit tests.
 */
interface FiltersAdapterInterface
{
    /**
     * Convert a source file to XLIFF via the Filters service.
     *
     * @param string      $filePath         Absolute path of the file to convert.
     * @param string      $sourceLang       Source language code.
     * @param string      $targetLang       Target language code.
     * @param string|null $segmentation     Segmentation rule, or null for the default.
     * @param IDto|null   $extractionParams Extraction parameters DTO, or null.
     * @param bool        $icu_enabled      Whether ICU message format segmentation is enabled.
     * @param bool|null   $legacy_icu       Whether to use legacy ICU escaping.
     *
     * @return mixed The Filters response array.
     */
    public function sourceToXliff(
        string  $filePath,
        string  $sourceLang,
        string  $targetLang,
        ?string $segmentation = null,
        ?IDto   $extractionParams = null,
        bool    $icu_enabled = false,
        ?bool   $legacy_icu = false,
    ): mixed;

    /**
     * Log a source-to-XLIFF conversion result (backup on failure, write to conversions log).
     *
     * @param array       $response            The Filters response array.
     * @param string      $sentFile            Absolute path of the file sent.
     * @param string      $sourceLang          Source language code.
     * @param string      $targetLang          Target language code.
     * @param string|null $segmentation        Segmentation rule used, or null.
     * @param IDto|null   $extractionParameters Extraction parameters DTO, or null.
     *
     * @throws Exception
     */
    public function logConversionToXliff(
        array   $response,
        string  $sentFile,
        string  $sourceLang,
        string  $targetLang,
        ?string $segmentation,
        ?IDto   $extractionParameters,
    ): void;
}

