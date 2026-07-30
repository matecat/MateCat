<?php

namespace Utils\Templating;

use ArrayAccess;
use JsonSerializable;
use Model\DataAccess\ArrayAccessTrait;
use Stringable;

/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 17/06/25
 * Time: 12:39
 *
 */
/**
 * @implements ArrayAccess<array-key, mixed>
 */
class PHPTalMap implements ArrayAccess, JsonSerializable, Stringable
{

    use ArrayAccessTrait;

    private const int JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;

    /** @var array<array-key, mixed> */
    private array $storage = [];

    /**
     * @param array<array-key, mixed> $values
     */
    public function __construct(array $values = [])
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $this->storage[] = new PHPTalMap($value);
                } else {
                    $this->storage[$key] = new PHPTalMap($value);
                }
            } else {
                $this->storage[$key] = $value;
            }
        }
    }

    public function __get(string $name): mixed
    {
        return $this->storage[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->storage[$name] = $value;
    }

    public function __toString(): string
    {
        // PHPTAL emits interpolations inside a <script> element verbatim, so the
        // encoded map has to be safe on its own: without JSON_HEX_TAG a value
        // containing "</script>" would close the element and turn the rest of the
        // page into markup. The remaining flags keep the literal from breaking out
        // of a surrounding quote. See PHPTalString for the single-value case.
        return json_encode($this->storage, self::JSON_FLAGS) ?: '{}';
    }

    /**
     * @return array<array-key, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->storage;
    }

}