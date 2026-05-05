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

class AnalysisFileMetadata implements JsonSerializable
{

    /**
     * @var string
     */
    protected string $key = "";

    /**
     * @var string
     */
    protected string $value = "";

    /**
     * AnalysisFileMetadata constructor.
     *
     * @param string $key
     * @param string $value
     */
    public function __construct(string $key, string $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
        ];
    }
}