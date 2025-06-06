<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 05/06/25
 * Time: 16:12
 *
 */

namespace Conversion;

use JsonSerializable;

class SimpleFileContent implements JsonSerializable {

    protected string $name;
    protected int    $size;

    /**
     * ZipContent constructor.
     *
     * @param string $name
     * @param int    $size
     */
    public function __construct( string $name, int $size ) {
        $this->name = $name;
        $this->size = $size;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getSize(): int {
        return $this->size;
    }

    public function jsonSerialize(): array {
        return [
                'name' => $this->name,
                'size' => $this->size
        ];
    }


}