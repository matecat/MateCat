<?php

namespace Conversion;

use Constants\ConversionHandlerStatus;
use Exception;
use JsonSerializable;

class ConvertedFileModel implements JsonSerializable {
    /**
     * @var int
     */
    private int $code = ConversionHandlerStatus::NOT_CONVERTED;

    /**
     * @var ?string
     */
    private ?string $error = null;

    /**
     * @var ?string
     */
    private ?string $warning = null;

    /**
     * @var ?string
     */
    private ?string $debug = null;

    /**
     * @var InternalHashPaths|null
     */
    private ?InternalHashPaths $_internal_path_data = null;

    /**
     * ConvertFileModel constructor.
     *
     * @param ?int $code
     *
     * @throws Exception
     */
    public function __construct( ?int $code = null ) {
        if ( !empty( $code ) ) {
            $this->changeCode( $code );
        }
    }

    /**
     * @param $code
     *
     * @return bool
     */
    private function validateCode( $code ): bool {
        $allowed = [
                ConversionHandlerStatus::ZIP_HANDLING,
                ConversionHandlerStatus::OK,
                ConversionHandlerStatus::NOT_CONVERTED,
                ConversionHandlerStatus::INVALID_FILE,
                ConversionHandlerStatus::NESTED_ZIP_FILES_NOT_ALLOWED,
                ConversionHandlerStatus::SOURCE_ERROR,
                ConversionHandlerStatus::TARGET_ERROR,
                ConversionHandlerStatus::UPLOAD_ERROR,
                ConversionHandlerStatus::MISCONFIGURATION,
                ConversionHandlerStatus::INVALID_TOKEN,
                ConversionHandlerStatus::INVALID_SEGMENTATION_RULE,
                ConversionHandlerStatus::OCR_WARNING,
                ConversionHandlerStatus::OCR_ERROR,
                ConversionHandlerStatus::GENERIC_ERROR,
                ConversionHandlerStatus::FILESYSTEM_ERROR,
                ConversionHandlerStatus::S3_ERROR,
        ];

        return in_array( $code, $allowed );
    }

    /**
     * @param $code
     *
     * @throws Exception
     */
    public function changeCode( $code ) {
        if ( !$this->validateCode( $code ) ) {
            throw new Exception( $code . ' is not a valid code' );
        }

        $this->code = $code;
    }

    /**
     * @return int
     */
    public function getCode(): int {
        return $this->code;
    }

    /**
     * @return bool
     */
    public function hasAnErrorCode(): bool {
        return $this->code <= 0;
    }

    /**
     * @param string  $messageError
     * @param ?string $debug
     */
    public function addError( string $messageError, ?string $debug = null ) {
        $this->error = $messageError;
        $this->debug = $debug;
    }

    /**
     * @return string
     */
    public function getError(): ?string {
        return $this->error;
    }

    /**
     * @return bool
     */
    public function hasErrors(): bool {
        return !empty( $this->error );
    }

    /**
     * @param string      $warningMessage
     * @param string|null $debug
     */
    public function addWarning( string $warningMessage, ?string $debug = null ) {
        $this->warning = $warningMessage;
        $this->debug   = $debug;
    }

    /**
     * @return string
     */
    public function getWarning(): ?string {
        return $this->warning;
    }

    /**
     * @return bool
     */
    public function hasWarnings() {
        return !empty( $this->warning );
    }

    public function getDebug(): ?string {
        return $this->debug;
    }

    /**
     * @param array $data
     *
     * @return void
     */
    public function addData( InternalHashPaths $data ) {
        $this->_internal_path_data = $data;
    }

    public function getData(): InternalHashPaths {
        return $this->_internal_path_data ?? new InternalHashPaths();
    }

    public function hasData(): bool {
        return !empty( $this->_internal_path_data ) && !$this->_internal_path_data->isEmpty();
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array {
        return [
                'code'    => $this->getCode(),
                'error'   => $this->getError(),
                'warning' => $this->getWarning(),
                'debug'   => $this->getDebug(),
        ];
    }
}