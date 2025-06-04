<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 30/05/25
 * Time: 17:37
 *
 */

namespace Conversion;

class ConvertedFileList {

    /**
     * @var ConvertedFileModel[]
     */
    private array $convertedFiles = [];

    public function add( ConvertedFileModel $convertedFileModel ): void {
        $this->convertedFiles[] = $convertedFileModel;
    }

    /**
     * @return bool
     */
    public function hasErrors(): bool {
        foreach ( $this->convertedFiles as $res ) {
            if ( $res->hasAnErrorCode() ) {
                return true;
            }
        }

        return false;
    }

    public function hasWarnings(): bool {
        foreach ( $this->convertedFiles as $res ) {
            if ( $res->hasAnErrorCode() ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check on executed conversion results and filter the stack to get errors only.
     * @return array
     */
    public function getErrors(): array {
        $errors = [];

        foreach ( $this->convertedFiles as $res ) {
            if ( $res->hasAnErrorCode() ) {
                $errors[] = $res;
            }
        }

        return $errors;
    }

    /**
     * Returns OCR warnings
     * @return array
     */
    public function getWarnings(): array {
        $warnings = [];
        foreach ( $this->convertedFiles as $res ) {
            if ( $res->hasAnErrorCode() ) {
                $warnings[] = $res;
            }
        }

        return $warnings;
    }

    public function getData(): array {
        $data = [];

        foreach ( $this->convertedFiles as $res ) {
            if ( $res->hasData() ) {
                $data[] = $res->getData();
            }
        }

        return $data;
    }

}