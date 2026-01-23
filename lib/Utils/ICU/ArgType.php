<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 22/01/26
 * Time: 13:56
 *
 */

namespace Utils\ICU;

/**
 * Argument type constants.
 * Returned by Part.getArgType() for ARG_START and ARG_LIMIT parts.
 *
 * Messages nested inside an argument are each delimited by MSG_START and MSG_LIMIT,
 * with a nesting level one greater than the surrounding message.
 *
 * Defines the argument type categories produced by the ICU MessagePattern parser.
 *
 * This enum models the logical kinds of arguments found while parsing ICU
 * MessageFormat patterns and is used to interpret ARG_START/ARG_LIMIT parts,
 * validate style semantics, and drive plural/select parsing rules within the
 * ICU utilities of the library.
 */
enum ArgType
{
    /**
     * The argument has no specified type.
     */
    case NONE;
    /**
     * The argument has a "simple" type which is provided by the ARG_TYPE part.
     * An ARG_STYLE part might follow that.
     */
    case SIMPLE;
    /**
     * The argument is a ChoiceFormat with one or more
     * ((ARG_INT | ARG_DOUBLE), ARG_SELECTOR, message) tuples.
     * @stable ICU 4.8
     */
    case CHOICE;
    /**
     * This argument takes the form of a cardinal-number PluralFormat, allowing for an optional offset like 'offset:1',
     * and requires at least one tuple consisting of an ARG_SELECTOR, an optional explicit value, and a message.
     * When the selector includes an explicit value (e.g., '=2'), the preceding ARG_INT or ARG_DOUBLE provides that value for the message.
     * If no explicit value is specified, the message directly follows the ARG_SELECTOR.
     */
    case PLURAL;
    /**
     * The argument is a SelectFormat with one or more (ARG_SELECTOR, message) pairs.
     */
    case SELECT;
    /**
     * The argument is an ordinal-number PluralFormat
     * with the same style parts sequence and semantics as {@link ArgType::PLURAL}.
     */
    case SELECTORDINAL;

    /**
     * @return true if the argument type has a plural style part sequence and semantics,
     * for example {@link ArgType::PLURAL} and {@link ArgType::SELECTORDINAL}.
     */
    public function hasPluralStyle(): bool
    {
        return match ($this) {
            self::PLURAL, self::SELECTORDINAL => true,
            default => false
        };
    }

}