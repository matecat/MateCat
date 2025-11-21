<?php

namespace Model\Conversion;

use Exception;
use Utils\Constants\ConversionHandlerStatus;

class ConvertedFileModel
{
    /**
     * @var int
     */
    private int $code = ConversionHandlerStatus::NOT_CONVERTED;

    /**
     * @var ?string
     */
    private ?string $message = null;

    /**
     * @var string
     */
    private string $name;

    /**
     * @var InternalHashPaths|null
     */
    private ?InternalHashPaths $_internal_conversion_hashes = null;

    private int $size = 0;

    private bool $isZipContent = false;

    private array $pdfAnalysis = [];

    /**
     * ConvertFileModel constructor.
     *
     * @param ?int $code
     *
     * @throws Exception
     */
    public function __construct(?int $code = null)
    {
        if (!empty($code)) {
            $this->setErrorCode($code);
        }
    }

    /**
     * @param int $code
     *
     * @return bool
     */
    private function validateCode(int $code): bool
    {
        $allowed = array_merge(ConversionHandlerStatus::errorCodes, ConversionHandlerStatus::warningCodes, [ConversionHandlerStatus::OK]);

        return in_array($code, $allowed);
    }

    /**
     * @param int $code
     *
     * @throws Exception
     */
    public function setErrorCode(int $code): void
    {
        if (!$this->validateCode($code)) {
            throw new Exception($code . ' is not a valid code');
        }

        $this->code = $code;
    }

    /**
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @return bool
     */
    public function isError(): bool
    {
        return in_array($this->code, ConversionHandlerStatus::errorCodes, true);
    }

    /**
     * @return bool
     */
    public function isWarning(): bool
    {
        return in_array($this->code, ConversionHandlerStatus::warningCodes, true);
    }

    /**
     * @param string $messageError
     */
    public function setErrorMessage(string $messageError): void
    {
        $this->message = $messageError;
    }

    /**
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @param bool   $isZipContent
     */
    public function setFileName(string $name, bool $isZipContent = false): void
    {
        $this->name = $name;
        $this->zipContent($isZipContent);
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function isZipContent(): bool
    {
        return $this->isZipContent;
    }

    /**
     * @param bool $isZipContent
     *
     */
    public function zipContent(bool $isZipContent = true): void
    {
        $this->isZipContent = $isZipContent;
    }

    /**
     * @param int $size
     *
     */
    public function setSize(int $size): void
    {
        $this->size = $size;
    }

    /**
     * @return array
     */
    public function getPdfAnalysis(): array
    {
        return $this->pdfAnalysis;
    }

    /**
     * @param array $pdfAnalysis
     */
    public function setPdfAnalysis(array $pdfAnalysis): void
    {
        $this->pdfAnalysis = $pdfAnalysis;
    }

    /**
     * @param InternalHashPaths $data
     *
     * @return void
     */
    public function addConversionHashes(InternalHashPaths $data): void
    {
        $this->_internal_conversion_hashes = $data;
    }

    /**
     * @return InternalHashPaths
     */
    public function getConversionHashes(): InternalHashPaths
    {
        return $this->_internal_conversion_hashes ?? new InternalHashPaths([]);
    }

    public function hasConversionHashes(): bool
    {
        return !empty($this->_internal_conversion_hashes) && !$this->_internal_conversion_hashes->isEmpty();
    }

    public function asError(): array
    {
        return [
                'code'    => $this->getCode(),
                'message' => $this->getMessage(),
                'name'    => $this->getName(),
        ];
    }

    public function getResult(): array
    {
        return [
                'name'        => $this->getName(),
                'size'        => $this->getSize(),
                'pdfAnalysis' => $this->getPdfAnalysis(),
        ];
    }

}