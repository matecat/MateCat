<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 07/07/25
 * Time: 14:27
 *
 */

namespace Utils\LQA;


/**
 * Class ErrObject
 * Object vector for error reporting.
 * json_encode facilities of public properties.
 *
 * __toString method are used for array_count_values and array_unique over container
 *
 */
class ErrObject {

    public ?int $outcome = null;
    public ?string $debug   = null;
    public string  $tip     = "";

    protected string $orig_debug;

    /**
     * Output externally the original debug string, needed for occurrence count
     * @return string
     */
    public function getOrigDebug(): string {
        return $this->orig_debug;
    }

    /**
     * Outputs externally the error tip
     * @return string
     */
    public function getTip() {
        return $this->tip;
    }

    /**
     * Static instance constructor
     *
     * @param array $errors
     *
     * @return ErrObject
     */
    public static function get( array $errors ): ErrObject {
        $errObj             = new self();
        $errObj->outcome    = $errors[ 'outcome' ];
        $errObj->orig_debug = $errors[ 'debug' ];
        $errObj->debug      = $errors[ 'debug' ];

        ( !empty( $errors[ 'tip' ] ) ) ? $errObj->tip = $errors[ 'tip' ] : null;

        return $errObj;
    }

    /**
     * Return string id
     * @return string
     */
    public function __toString() {
        return (string)$this->outcome;
    }

}