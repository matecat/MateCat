<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 22/01/26
 * Time: 14:28
 *
 */

namespace Utils\ICU;


/**
 * A message pattern "part", representing a pattern parsing event.
 * There is a part for the start and end of a message or argument,
 * for quoting and escaping of and with ASCII apostrophes,
 * and for syntax elements of "complex" arguments.
 */
final class Part
{
    /**
     * @var ArgType[]
     */
    private static array $argTypes = [
        // fill with ArgType enum instances in ordinal order
        // e.g., ArgType::NONE, ArgType::SOME_TYPE, ...
    ];

    public static int $MAX_LENGTH = 0xffff;
    public static int $MAX_VALUE = 32767;

    public function __construct(
        private readonly Type $type,
        private readonly int $index,
        private readonly int $length,
        private readonly int $value
    ) {
        self::$argTypes = ArgType::cases();
    }

    /**
     * Returns the type of this part.
     * Returns: the part type.
     */
    public function getType(): Type
    {
        return $this->type;
    }

    /**
     * Returns the pattern string index associated with this Part.
     * Returns: this part's pattern string index.
     */
    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * Returns the length of the pattern substring associated with this Part. This is 0 for some parts.
     * Returns: this part's pattern substring length.
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * Returns the pattern string limit (exclusive-end) index associated with this Part. Convenience method for getIndex()+getLength().
     * Returns: this part's pattern string limit index, same as getIndex()+getLength().
     */
    public function getLimit(): int
    {
        return $this->index + $this->length;
    }

    /**
     * Returns a value associated with this part. See the documentation of each part type for details.
     * Returns: the part value.
     */
    public function getValue(): int
    {
        return $this->value;
    }

    /**
     * Returns the argument type if this part is of type ARG_START or ARG_LIMIT, otherwise ArgType.NONE.
     * Returns: the argument type for this part.
     */
    public function getArgType(): ArgType
    {
        return match ($this->getType()) {
            Type::ARG_START,
            Type::ARG_LIMIT => self::$argTypes[$this->value] ?? ArgType::NONE,
            default => ArgType::NONE,
        };
    }

    /**
     * @return a string representation of this part.
     * @stable ICU 4.8
     */
    public function __toString(): string
    {
        $valueString = ($this->type == Type::ARG_START || $this->type == Type::ARG_LIMIT) ? $this->getArgType()->name : $this->value;
        return $this->type->name . "(" . $valueString . ")@" . $this->index;
    }

}