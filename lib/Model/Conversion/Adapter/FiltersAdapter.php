<?php
/**
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 27/02/26
 * Time: 10:00
 */

namespace Model\Conversion\Adapter;

use Exception;
use Model\Conversion\Filters;
use Model\Filters\DTO\IDto;

/**
 * Production implementation of {@see FiltersAdapterInterface}.
 *
 * Delegates every call to the static methods on {@see Filters},
 * preserving the existing HTTP/cURL and MySQL behaviour.
 */
class FiltersAdapter implements FiltersAdapterInterface
{
    /**
     * {@inheritDoc}
     */
    public function sourceToXliff(
        string  $filePath,
        string  $sourceLang,
        string  $targetLang,
        ?string $segmentation = null,
        ?IDto   $extractionParams = null,
        bool    $icu_enabled = false,
        ?bool   $legacy_icu = false,
    ): mixed {
        return Filters::sourceToXliff(
            $filePath,
            $sourceLang,
            $targetLang,
            $segmentation,
            $extractionParams,
            $icu_enabled,
            $legacy_icu,
        );
    }

    /**
     * {@inheritDoc}
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
    ): void {
        Filters::logConversionToXliff(
            $response,
            $sentFile,
            $sourceLang,
            $targetLang,
            $segmentation,
            $extractionParameters,
        );
    }
}

