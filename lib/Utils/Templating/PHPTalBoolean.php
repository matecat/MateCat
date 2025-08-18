<?php

namespace Utils\Templating;
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 17/06/25
 * Time: 16:11
 *
 */
class PHPTalBoolean {

    private bool $value;

    /**
     * @param bool $value
     */
    public function __construct( bool $value ) {
        $this->value = $value;
    }


    public function __toString() {
        return $this->value ? 'true' : 'false';
    }

}