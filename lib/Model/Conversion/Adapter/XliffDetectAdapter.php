<?php
/**
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 27/02/26
 * Time: 10:00
 */

namespace Model\Conversion\Adapter;

use Matecat\XliffParser\XliffUtils\XliffProprietaryDetect;

/**
 * Production implementation of {@see XliffDetectAdapterInterface}.
 *
 * Delegates every call to the static methods on the vendor class
 * {@see XliffProprietaryDetect}.
 */
class XliffDetectAdapter implements XliffDetectAdapterInterface
{
    /**
     * {@inheritDoc}
     */
    public function fileMustBeConverted(string $filePath, bool $enforceXliff, string $filtersAddress): bool|int
    {
        return XliffProprietaryDetect::fileMustBeConverted($filePath, $enforceXliff, $filtersAddress);
    }

    /**
     * {@inheritDoc}
     */
    public function getInfo(string $filePath): array
    {
        return XliffProprietaryDetect::getInfo($filePath);
    }
}

