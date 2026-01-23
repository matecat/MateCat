<?php
/**
 * Created by PhpStorm.
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 * Date: 22/01/26
 * Time: 14:32
 *
 */

namespace Utils\ICU;

/**
 * Defines the part categories emitted by the ICU MessagePattern parser.
 *
 * This enum represents the structural markers and syntax tokens recorded while
 * parsing MessageFormat patterns. It is used to interpret the parts list for
 * message boundaries, argument boundaries, selectors, and numeric values inside
 * the ICU utilities of the library.
 */
enum Type
{
    /**
     * Start a message pattern (main or nested).
     * The length is 0 for the top-level message
     * and for a choice argument sub-message, otherwise 1 for the '{'.
     * The value indicates the nesting level, starting with 0 for the main message.
     * <p>
     * There is always a later MSG_LIMIT part.
     *
     */
    case MSG_START;

    /**
     * End of a message pattern (main or nested).
     * The length is 0 for the top-level message and
     * the last sub-message of a choice argument,
     * otherwise 1 for the '}' or (in a choice argument style) the '|'.
     * The value indicates the nesting level, starting with 0 for the main message.
     *
     */
    case MSG_LIMIT;

    /**
     * Indicates a substring of the pattern string which is to be skipped when formatting.
     * For example, an apostrophe that begins or ends with a quoted text
     * would be indicated with such a part.
     * The value is undefined and currently always 0.
     *
     */
    case SKIP_SYNTAX;

    /**
     * Indicates that a syntax character needs to be inserted for auto-quoting.
     * The length is 0.
     * The value is the character code of the insertion character. (U+0027=APOSTROPHE)
     *
     */
    case INSERT_CHAR;

    /**
     * Indicates a syntactic (non-escaped) # symbol in a plural variant.
     * When formatting, replace this part's substring with the
     * (value-offset) for the plural argument value.
     * The value is undefined and currently always 0.
     *
     */
    case REPLACE_NUMBER;

    /**
     * Start an argument.
     * The length is 1 for the '{'.
     * The value is the ordinal value of the ArgType. Use getArgType().
     * <p>
     * This part is followed by either an ARG_NUMBER or ARG_NAME,
     * followed by optional argument subparts (see ArgType constants)
     * and finally an ARG_LIMIT part.
     *
     */
    case ARG_START;

    /**
     * End of an argument.
     * The length is 1 for the '}'.
     * The value is the ordinal value of the ArgType. Use getArgType().
     *
     */
    case ARG_LIMIT;

    /**
     * The argument number, provided by the value.
     *
     */
    case ARG_NUMBER;

    /**
     * The argument name.
     * The value is undefined and currently always 0.
     *
     */
    case ARG_NAME;

    /**
     * The argument type.
     * The value is undefined and currently always 0.
     *
     */
    case ARG_TYPE;

    /**
     * The argument style text.
     * The value is undefined and currently always 0.
     *
     */
    case ARG_STYLE;

    /**
     * A selector substring in a "complex" argument style.
     * The value is undefined and currently always 0.
     *
     */
    case ARG_SELECTOR;

    /**
     * An integer value, for example, the offset or an explicit selector value
     * in a PluralFormat style.
     * The part value is the integer value.
     *
     */
    case ARG_INT;

    /**
     * A numeric value, for example, the offset or an explicit selector value
     * in a PluralFormat style.
     * The part value is an index into an internal array of numeric values;
     * use getNumericValue().
     *
     */
    case ARG_DOUBLE;

    /**
     * Indicates whether this part has a numeric value.
     * If so, then that numeric value can be retrieved via MessagePattern::getNumericValue(Part).
     * @return bool true if this part has a numeric value.
     *
     */
    public function hasNumericValue(): bool
    {
        return match ($this) {
            self::ARG_INT, self::ARG_DOUBLE => true,
            default => false,
        };
    }
}