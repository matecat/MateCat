<?php

namespace Utils\Templating;

use Stringable;

/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 17/06/25
 * Time: 16:11
 *
 */
class PHPTalBoolean implements Stringable
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

}