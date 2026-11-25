<?php

namespace Utils\Templating;

use JsonSerializable;
use Stringable;

/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 17/06/25
 * Time: 16:11
 *
 */
class PHPTalBoolean implements Stringable, JsonSerializable
{

    private bool $value;

    /**
     * @param bool $value
     */
    public function __construct(bool $value)
    {
        $this->value = $value;
    }


    public function __toString(): string
    {
        return $this->value ? 'true' : 'false';
    }

    /**
     * Handed back as a real boolean, so that a whole-payload json_encode() emits true/false rather
     * than the object, and the page receives a boolean instead of the string "true".
     *
     * @return bool
     */
    public function jsonSerialize(): bool
    {
        return $this->value;
    }

}