<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 30/05/25
 * Time: 17:37
 *
 */

namespace Model\Conversion;

class ConvertedFileList
{

    /**
     * @var ConvertedFileModel[]
     */
    private array $convertedFiles = [];
    private array $erroredFiles = [];
    private array $warnedFiles = [];

    public function add(ConvertedFileModel $convertedFileModel): void
    {
        $this->convertedFiles[] = $convertedFileModel;
    }

    /**
     * @param ConvertedFileModel $warnedFile
     */
    public function setWarnedFile(ConvertedFileModel $warnedFile): void
    {
        $this->warnedFiles[] = $warnedFile->asError();
    }

    /**
     * @param ConvertedFileModel $erroredFile
     *
     */
    public function setErroredFile(ConvertedFileModel $erroredFile): void
    {
        $this->erroredFiles[] = $erroredFile->asError();
    }

    /**
     * @return bool
     */
    public function hasErrors(): bool
    {
        return !empty($this->erroredFiles);
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnedFiles);
    }

    /**
     * Check on executed conversion results and filter the stack to get errors only.
     * @return array
     */
    public function getErrors(): array
    {
        return $this->erroredFiles;
    }

    /**
     * Returns OCR warnings
     * @return array
     */
    public function getWarnings(): array
    {
        return $this->warnedFiles;
    }

    /**
     * @return InternalHashPaths[]
     */
    public function getHashes(): array
    {
        $hashes = [];
        foreach ($this->convertedFiles as $res) {
            if ($res->hasConversionHashes()) {
                $hashes[] = $res->getConversionHashes();
            }
        }

        return $hashes;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        $data = [];

        foreach ($this->convertedFiles as $res) {
            if ($res->isZipContent()) {
                $data['zipFiles'][] = $res->getResult();
            } else {
                $data['simpleFileName'][] = $res->getResult();
            }
        }

        return $data;
    }

}