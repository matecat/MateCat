<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 13/11/23
 * Time: 19:11
 *
 */

namespace View\API\App\Json\Analysis;

use JsonSerializable;
use Utils\Tools\Utils;

class AnalysisFileMetadata implements JsonSerializable {

    /**
     * @var string
     */
    protected string $key = "";

    /**
     * @var mixed
     */
    protected $value;

    public function __construct( string $key, $value ) {
        $this->key   = $key;
        $this->value = $value;
    }

    public function jsonSerialize(): array {
        return [
                'key'   => $this->key,
                'value' => Utils::formatStringValue( $this->value ),
        ];
    }
}