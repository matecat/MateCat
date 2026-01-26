<?php

namespace Utils\ICU;

use InvalidArgumentException;
use Iterator;
use OutOfBoundsException;

/**
 * This is a porting of The ICU MessageFormat Parser by Markus Scherer:
 *
 * @link https://github.com/prepare/icu4j/blob/master/main/classes/core/src/com/ibm/icu/text/MessagePattern.java
 *
 * Parses and represents ICU MessageFormat patterns.
 * Also handles patterns for ChoiceFormat, PluralFormat, and SelectFormat.
 * Used in the implementations of those classes as well as in tools
 * for message validation, translation, and format conversion.
 * <p>
 * The parser handles all syntax relevant for identifying message arguments.
 * This includes "complex" arguments whose style strings contain
 * nested MessageFormat pattern substrings.
 * For "simple" arguments (with no nested MessageFormat pattern substrings),
 * the argument style is not parsed any further.
 * <p>
 * The parser handles named and numbered message arguments and allows both in one message.
 * <p>
 * Once a pattern has been parsed successfully, iterate through the parsed data
 * with countParts(), getPart() and related methods.
 * <p>
 * The data logically represents a parse tree but is stored and accessed
 * as a list of "parts" for fast and simple parsing and to minimize object allocations.
 * Arguments and nested messages are best handled via recursion.
 * For every _START "part", getLimitPartIndex(int) efficiently returns
 * the index of the corresponding _LIMIT "part".
 * <p>
 * List of "parts":
 * <pre>
 * message = MSG_START (SKIP_SYNTAX | INSERT_CHAR | REPLACE_NUMBER | argument)* MSG_LIMIT
 * argument = noneArg | simpleArg | complexArg
 * complexArg = choiceArg | pluralArg | selectArg
 *
 * noneArg = ARG_START.NONE (ARG_NAME | ARG_NUMBER) ARG_LIMIT.NONE
 * simpleArg = ARG_START.SIMPLE (ARG_NAME | ARG_NUMBER) ARG_TYPE [ARG_STYLE] ARG_LIMIT.SIMPLE
 * choiceArg = ARG_START.CHOICE (ARG_NAME | ARG_NUMBER) choiceStyle ARG_LIMIT.CHOICE
 * pluralArg = ARG_START.PLURAL (ARG_NAME | ARG_NUMBER) pluralStyle ARG_LIMIT.PLURAL
 * selectArg = ARG_START.SELECT (ARG_NAME | ARG_NUMBER) selectStyle ARG_LIMIT.SELECT
 *
 * choiceStyle = ((ARG_INT | ARG_DOUBLE) ARG_SELECTOR message)+
 * pluralStyle = [ARG_INT | ARG_DOUBLE] (ARG_SELECTOR [ARG_INT | ARG_DOUBLE] message)+
 * selectStyle = (ARG_SELECTOR message)+
 * </pre>
 * <ul>
 *   <li>Literal output text is not represented directly by "parts" but accessed
 *       between parts of a message, from one part's getLimit() to the next part's getIndex().
 *   <li>ARG_START.CHOICE stands for an ARG_START Part with ArgType CHOICE.
 *   <li>In the choiceStyle, the ARG_SELECTOR has the '<', the '#' or
 *       the less-than-or-equal-to sign (U+2264).
 *   <li>In the pluralStyle, the first, optional numeric Part has the "offset:" value.
 *       The optional numeric Part between each (ARG_SELECTOR, message) pair
 *       is the value of an explicit-number selector like "=2",
 *       otherwise the selector is a non-numeric identifier.
 *   <li>The REPLACE_NUMBER Part can occur only in an immediate sub-message of the pluralStyle.
 * </ul>
 * <p>
 * This class is not intended for public subclassing.
 *
 * @author Domenico Lupinetti (hashashiyyin) domenico@translated.net / ostico@gmail.com
 *
 */
final class MessagePattern implements Iterator
{
    /**
     * Return value from validateArgumentName() for when
     * the string is a valid "pattern identifier" but not a number.
     */
    public const int ARG_NAME_NOT_NUMBER = -1;
    /**
     * Return value from validateArgumentName() for when
     * the string is invalid.
     * It might not be a valid "pattern identifier",
     * or it has only ASCII digits, but there is a leading zero or the number is too large.
     */
    public const int ARG_NAME_NOT_VALID = -2;
    /**
     * Return value indicating that the argument value exceeds
     * the allowed or expected range.
     */
    public const int ARG_VALUE_OVERFLOW = -3;

    /**
     * Special value that is returned by getNumericValue(Part) when no
     * numeric value is defined for a part.
     * @see getNumericValue()
     */
    public const float NO_NUMERIC_VALUE = -123456789.0;
    /**
     * A literal apostrophe is represented by either a single or a double apostrophe pattern character.
     * Within a MessageFormat pattern, a single apostrophe only starts quoted literal text
     * if it immediately precedes a curly brace {}, or a pipe symbol | if inside a choice format,
     * or a pound symbol # if inside a plural format.
     * <p>
     * This is the default behavior starting with ICU 4.8.
     */
    public const string APOSTROPHE_DOUBLE_OPTIONAL = 'DOUBLE_OPTIONAL';
    /**
     * A literal apostrophe must be represented by a double apostrophe pattern character.
     * A single apostrophe always starts quoted literal text.
     * <p>
     * This is the behavior of ICU 4.6 and earlier, and of java.text.MessageFormat.
     */
    public const string APOSTROPHE_DOUBLE_REQUIRED = 'DOUBLE_REQUIRED';

    private string $msg = '';
    private bool $hasArgNames = false;
    private bool $hasArgNumbers = false;
    private bool $needsAutoQuoting = false;

    /**
     * @var Part[]
     */
    private array $parts = [];

    /**
     * @var float[]
     */
    private array $numericValues = [];

    /**
     * @var int[]
     */
    private array $limitPartIndexes = [];

    private string $aposMode;

    private const string Pattern_White_Space = '\x{0009}-\x{000D}\x{0020}\x{0085}\x{200E}\x{200F}\x{2028}\x{2029}';
    private const string Pattern_Identifier = '\x{0021}-\x{002F}\x{003A}-\x{0040}\x{005B}-\x{005E}\x{0060}\x{007B}-\x{007E}\x{00A1}-\x{00A7}\x{00A9}\x{00AB}\x{00AC}\x{00AE}\x{00B0}\x{00B1}\x{00B6}\x{00BB}\x{00BF}\x{00D7}\x{00F7}\x{2010}-\x{2027}\x{2030}-\x{203E}\x{2041}-\x{2053}\x{2055}-\x{205E}\x{2190}-\x{245F}\x{2500}-\x{2775}\x{2794}-\x{2BFF}\x{2E00}-\x{2E7F}\x{3001}-\x{3003}\x{3008}-\x{3020}\x{3030}\x{FD3E}\x{FD3F}\x{FE45}\x{FE46}';
    /**
     * @var string[]
     */
    private array $chars = [];

    private int $msgLength = 0;


    public function __construct(?string $pattern = null, string $apostropheMode = self::APOSTROPHE_DOUBLE_OPTIONAL)
    {
        $this->aposMode = $apostropheMode;
        if (!empty($pattern)) {
            $this->parse($pattern);
        }
    }

    /**
     * Parses a MessageFormat pattern string.
     * @param string $pattern a MessageFormat pattern string
     * @return $this
     * @throws InvalidArgumentException for syntax errors in the pattern string
     * @throws OutOfBoundsException if certain limits are exceeded
     *         (e.g., argument number too high, argument name too long, etc.)
     * @throws InvalidArgumentException if a number could not be parsed
     */
    public function parse(string $pattern): self
    {
        $this->preParse($pattern);
        $this->parseMessage(0, 0, 0, ArgType::NONE);
        $this->postParse();
        return $this;
    }

    /**
     * Parses a ChoiceFormat pattern string.
     * @param string $pattern a ChoiceFormat pattern string
     * @return $this
     * @throws InvalidArgumentException for syntax errors in the pattern string
     * @throws OutOfBoundsException if certain limits are exceeded
     * @throws InvalidArgumentException if a number could not be parsed
     */
    public function parseChoiceStyle(string $pattern): self
    {
        $this->preParse($pattern);
        $this->parseChoiceStyleInternal(0, 0);
        $this->postParse();
        return $this;
    }

    /**
     * Parses a PluralFormat pattern string.
     * @param string $pattern a PluralFormat pattern string
     * @return $this
     * @throws InvalidArgumentException for syntax errors in the pattern string
     * @throws OutOfBoundsException if certain limits are exceeded
     * @throws InvalidArgumentException if a number could not be parsed
     */
    public function parsePluralStyle(string $pattern): self
    {
        $this->preParse($pattern);
        $this->parsePluralOrSelectStyle(ArgType::PLURAL, 0, 0);
        $this->postParse();
        return $this;
    }

    /**
     * Parses a SelectFormat pattern string.
     * @param string $pattern a SelectFormat pattern string
     * @return $this
     * @throws InvalidArgumentException for syntax errors in the pattern string
     * @throws OutOfBoundsException if certain limits are exceeded
     * @throws InvalidArgumentException if a number could not be parsed
     */
    public function parseSelectStyle(string $pattern): self
    {
        $this->preParse($pattern);
        $this->parsePluralOrSelectStyle(ArgType::SELECT, 0, 0);
        $this->postParse();
        return $this;
    }

    /**
     * Clears this MessagePattern.
     * countParts() will return 0.
     */
    public function clear(): void
    {
        $this->msg = '';
        $this->hasArgNames = false;
        $this->hasArgNumbers = false;
        $this->needsAutoQuoting = false;
        $this->parts = [];
        $this->numericValues = [];
        $this->limitPartIndexes = [];
    }

    /**
     * Clears this MessagePattern and sets the ApostropheMode.
     * countParts() will return 0.
     * @param string $mode The new ApostropheMode.
     */
    public function clearPatternAndSetApostropheMode(string $mode): void
    {
        $this->clear();
        $this->aposMode = $mode;
    }

    /**
     * @return string this instance's ApostropheMode.
     */
    public function getApostropheMode(): string
    {
        return $this->aposMode;
    }

    /**
     * @return string the parsed pattern string (empty if none was parsed).
     */
    public function getPatternString(): string
    {
        return $this->msg;
    }

    /**
     * Does the parsed pattern have named arguments like {first_name}?
     * @return bool true if the parsed pattern has at least one named argument.
     */
    public function hasNamedArguments(): bool
    {
        return $this->hasArgNames;
    }

    /**
     * Does the parsed pattern have numbered arguments like {2}?
     * @return bool true if the parsed pattern has at least one numbered argument.
     */
    public function hasNumberedArguments(): bool
    {
        return $this->hasArgNumbers;
    }

    /**
     * Returns the number of "parts" created by parsing the pattern string.
     * Returns 0 if no pattern has been parsed or clear() was called.
     * @return int the number of pattern parts.
     */
    public function countParts(): int
    {
        return count($this->parts);
    }

    /**
     * Gets the i-th pattern "part".
     * @param int $i The index of the Part data. (0…countParts()-1)
     * @return Part the i-th pattern "part".
     * @throws OutOfBoundsException if the index i is outside the (0…countParts()-1) range
     */
    public function getPart(int $i): Part
    {
        if (!isset($this->parts[$i])) {
            throw new OutOfBoundsException('Part index out of range.');
        }
        return $this->parts[$i];
    }

    /**
     * Returns the Part.Type of the i-th pattern "part".
     * Convenience method for getPart(i)->getType().
     * @param int $i The index of the Part data. (0…countParts()-1)
     * @return Type The Part.Type of the i-th Part.
     * @throws OutOfBoundsException if the index i is outside the (0...countParts()-1) range
     */
    public function getPartType(int $i): Type
    {
        return $this->getPart($i)->getType();
    }

    /**
     * Returns the pattern index of the specified pattern "part".
     * Convenience method for getPart(partIndex)->getIndex().
     * @param int $partIndex The index of the Part data. (0...countParts()-1)
     * @return int The pattern index of this Part.
     * @throws OutOfBoundsException if partIndex is outside the (0...countParts()-1) range
     */
    public function getPatternIndex(int $partIndex): int
    {
        return $this->getPart($partIndex)->getIndex();
    }

    /**
     * Returns the substring of the pattern string indicated by the Part.
     * Convenience method for getPatternString()->substring(part->getIndex(), part->getLimit()).
     * @param Part $part a part of this MessagePattern.
     * @return string the substring associated with part.
     */
    public function getSubstring(Part $part): string
    {
        return implode('', array_slice($this->chars, $part->getIndex(), $part->getLength()));
    }

    /**
     * Compares the part's substring with the input string s.
     * @param Part $part a part of this MessagePattern.
     * @param string $s a string.
     * @return bool true if getSubstring(part) == s.
     */
    public function partSubstringMatches(Part $part, string $s): bool
    {
        return $part->getLength() === mb_strlen($s)
            && implode('', array_slice($this->chars, $part->getIndex(), $part->getLength())) === $s;
    }

    /**
     * Returns the numeric value associated with an ARG_INT or ARG_DOUBLE.
     * @param Part $part a part of this MessagePattern.
     * @return float the part's numeric value, or NO_NUMERIC_VALUE if this is not a numeric part.
     */
    public function getNumericValue(Part $part): float
    {
        $type = $part->getType();
        if ($type === Type::ARG_INT) {
            return (float)$part->getValue();
        }
        if ($type === Type::ARG_DOUBLE) {
            return $this->numericValues[$part->getValue()] ?? self::NO_NUMERIC_VALUE;
        }
        return self::NO_NUMERIC_VALUE;
    }

    /**
     * Returns the "offset:" value of a PluralFormat argument, or 0 if none is specified.
     * @param int $pluralStart the index of the first PluralFormat argument style part. (0...countParts()-1)
     * @return float the "offset:" value.
     * @throws OutOfBoundsException if pluralStart is outside the (0...countParts()-1) range
     */
    public function getPluralOffset(int $pluralStart): float
    {
        $part = $this->getPart($pluralStart);
        if ($part->getType()->hasNumericValue()) {
            return $this->getNumericValue($part);
        }
        return 0.0;
    }

    /**
     * Returns the index of the ARG|MSG_LIMIT part corresponding to the ARG|MSG_START at start.
     * @param int $start The index of some Part data (0...countParts()-1);
     *        this Part should be of Type ARG_START or MSG_START.
     * @return int The first i>start where getPart(i)->getType()==ARG|MSG_LIMIT at the same nesting level,
     *         or start itself if getPartType(msgStart)!=ARG|MSG_START.
     * @throws OutOfBoundsException if start is outside the (0...countParts()-1) range
     */
    public function getLimitPartIndex(int $start): int
    {
        return $this->limitPartIndexes[$start] ?? $start;
    }

    /**
     * Returns a version of the parsed pattern string where each ASCII apostrophe
     * is doubled (escaped) if it is not already, and if it is not interpreted as quoting syntax.
     * <p>
     *    For example, this turns "I don't '{know}' {gender,select,female{h''er}other{h'im}}."
     *    into "I don''t '{know}' {gender,select,female{h''er}other{h''im}}."
     * </p>
     * @return string the deep-auto-quoted version of the parsed pattern string.
     */
    public function autoQuoteApostropheDeep(): string
    {
        // Fast path: nothing to auto-quote, return original message.
        if (!$this->needsAutoQuoting) {
            return $this->msg;
        }

        // Start from the original message and apply insertions.
        $modified = $this->msg;

        // Walk parts in reverse so earlier insertions don't shift later indices.
        foreach (array_reverse(iterator_to_array($this, false)) as $part) {
            if ($part->getType() === Type::INSERT_CHAR) {
                $index = $part->getIndex();
                $char = mb_chr($part->getValue());

                // Insert the character at the recorded index (multibyte-safe).
                $modified = mb_substr($modified, 0, $index) . $char . mb_substr($modified, $index);
            }
        }

        // Return the fully auto-quoted message.
        return $modified;
    }

    /**
     * Validates and parses an argument name or argument number string.
     * An argument name must be a "pattern identifier", that is, it must contain
     * no Unicode Pattern_Syntax or Pattern_White_Space characters.
     * If it only contains ASCII digits, then it must be a small integer with no leading zero.
     * @param string $name Input string.
     * @return int >=0 if the name is a valid number,
     *         ARG_NAME_NOT_NUMBER (-1) if it is a "pattern identifier" but not all ASCII digits,
     *         ARG_NAME_NOT_VALID (-2) if it is neither.
     */
    public static function validateArgumentName(string $name): int
    {
        if (!self::isIdentifier($name)) {
            return self::ARG_NAME_NOT_VALID;
        }
        return self::parseArgNumberFromString($name, 0, mb_strlen($name));
    }

    /**
     * Appends a string segment to the output, reducing consecutive apostrophes.
     * Doubled apostrophes (escaped single quotes) are treated as a single apostrophe.
     *
     * @param string $s The input string containing apostrophes to process.
     * @param int $start The starting index of the segment within the input string.
     * @param int $limit The ending index (exclusive) of the segment within the input string.
     * @param string &$out A reference to the output string where the processed result will be appended.
     * @return void
     */
    public static function appendReducedApostrophes(string $s, int $start, int $limit, string &$out): void
    {
        // Track the position of a potential doubled apostrophe (escaped single quote)
        $doubleApos = -1;
        while (true) {
            // Find next apostrophe starting from current position
            $i = mb_strpos($s, "'", $start);
            if ($i === false || $i >= $limit) {
                // No more apostrophes in range: append the remaining segment and finish
                $out .= mb_substr($s, $start, $limit - $start);
                break;
            }
            if ($i === $doubleApos) {
                // Second apostrophe of a doubled pair: emit one apostrophe
                $out .= "'";
                $start++;
                $doubleApos = -1;
            } else {
                // Append text up to apostrophe and mark next char as a possible pair
                $out .= mb_substr($s, $start, $i - $start);
                $doubleApos = $start = $i + 1;
            }
        }
    }

    /**
     * Prepares the instance for parsing a pattern.
     * @param string $pattern Pattern to parse.
     */
    private function preParse(string $pattern): void
    {
        $this->msg = $pattern;
        $this->hasArgNames = false;
        $this->hasArgNumbers = false;
        $this->needsAutoQuoting = false;
        $this->parts = [];
        $this->numericValues = [];
        $this->limitPartIndexes = [];
        $this->chars = preg_split('//u', $pattern, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $this->msgLength = mb_strlen($pattern);
    }

    /**
     * Post-parse hook. Currently, no-op.
     */
    private function postParse(): void
    {
        // No post-processing required.
    }

    /**
     * Parses a message fragment within a specified range of a message string.
     * Handles nested structures, quoting rules, and special characters.
     *
     * @param int $index The starting index in the message string.
     * @param int $msgStartLength The length of the prefix to skip (e.g., '{').
     * @param int $nestingLevel The current level of nesting in the message structure.
     * @param ArgType $parentType The type of the parent argument, used to determine parsing behavior.
     * @return int The index after processing the message fragment or its terminator.
     * @throws OutOfBoundsException If the nesting level exceeds the maximum allowable value.
     * @throws InvalidArgumentException If an unmatched brace is found in the nested structure.
     */
    private function parseMessage(int $index, int $msgStartLength, int $nestingLevel, ArgType $parentType): int
    {
        // Guard against excessive nesting that would overflow stored part values.
        if ($nestingLevel > 100) {
            throw new OutOfBoundsException("Nesting level exceeds maximum value");
        }

        // Record the start of this message fragment and advance past any prefix (e.g., '{').
        $msgStart = count($this->parts);
        $this->addPart(Type::MSG_START, $index, $msgStartLength, $nestingLevel);
        $index += $msgStartLength;

        $length = $this->msgLength;
        while ($index < $length) {
            $c = $this->charAt($index++);
            if ($c === "'") {
                // Handle apostrophe quoting rules and auto-quoting insertions.
                if ($index === $length) {
                    $this->addPart(Type::INSERT_CHAR, $index, 0, 0x27 /* ord("'") */);
                    $this->needsAutoQuoting = true;
                } else {
                    $c = $this->charAt($index);
                    if ($c === "'") {
                        // Double apostrophe: treat as escaped apostrophe.
                        $this->addPart(Type::SKIP_SYNTAX, $index++, 1, 0);
                    } elseif (
                        $this->aposMode === self::APOSTROPHE_DOUBLE_REQUIRED ||
                        $c === '{' || $c === '}' ||
                        ($parentType === ArgType::CHOICE && $c === '|') ||
                        ($parentType->hasPluralStyle() && $c === '#')
                    ) {
                        // Start quoted literal section; skip the opening quote and scan to closing quote.
                        $this->addPart(Type::SKIP_SYNTAX, $index - 1, 1, 0);
                        while (true) {
                            // Seek for the next apostrophe using a pre-split chars array
                            for ($i = $index + 1; $i < $length; $i++) {
                                if ($this->charAt($i) === "'") {
                                    $index = $i;
                                    if (($index + 1) < $length && $this->charAt($index + 1) === "'") {
                                        $this->addPart(Type::SKIP_SYNTAX, ++$index, 1, 0);
                                        continue 2; // Continue outer while loop
                                    } else {
                                        $this->addPart(Type::SKIP_SYNTAX, $index++, 1, 0);
                                        break 2; // Break outer while loop
                                    }
                                }
                            }
                            // If the loop completes without finding apostrophe: unterminated quote
                            $index = $length;
                            $this->addPart(Type::INSERT_CHAR, $index, 0, 0x27 /* ord("'") */);
                            $this->needsAutoQuoting = true;
                            break;
                        }
                    } else {
                        // Apostrophe is literal text; mark for auto-quoting.
                        $this->addPart(Type::INSERT_CHAR, $index, 0, 0x27 /* ord("'") */);
                        $this->needsAutoQuoting = true;
                    }
                }
            } elseif ($parentType->hasPluralStyle() && $c === '#') {
                // Unquoted # in plural/selectordinal: mark for number replacement.
                $this->addPart(Type::REPLACE_NUMBER, $index - 1, 1, 0);
            } elseif ($c === '{') {
                // Parse nested argument and continue from its end.
                $index = $this->parseArg($index - 1, $nestingLevel);
            } elseif (($nestingLevel > 0 && $c === '}') || ($parentType === ArgType::CHOICE && $c === '|')) {
                // End of this message fragment at '}' or choice separator '|'.
                $limitLength = ($parentType === ArgType::CHOICE && $c === '}') ? 0 : 1;
                $this->addLimitPart($msgStart, Type::MSG_LIMIT, $index - 1, $limitLength, $nestingLevel);
                if ($parentType === ArgType::CHOICE) {
                    return $index - 1; // Let caller handle terminator.
                }
                return $index; // Resume after closing brace.
            }
        }

        // If nested and not a valid top-level choice sub-message, report unmatched '{'.
        if ($nestingLevel > 0 && !$this->inTopLevelChoiceMessage($nestingLevel, $parentType)) {
            throw new InvalidArgumentException("Unmatched '{' braces in message " . $this->prefix());
        }

        // Close the message fragment and return the current index (end or terminator).
        $this->addLimitPart($msgStart, Type::MSG_LIMIT, $index, 0, $nestingLevel);
        return $index;
    }

    /**
     * Parses an argument placeholder within a message pattern.
     *
     * This method starts at a given position in the input string, validating
     * and interpreting the argument's syntax, type, and optional styles. It handles
     * both numeric and named arguments, validates their structure, and records the necessary
     * information for later processing or rendering.
     *
     * @param int $index The current position in the message pattern where the argument starts.
     * @param int $nestingLevel The current nesting depth of braces in the message pattern.
     * @return int The position in the message pattern immediately after the closing '}' of the argument.
     * @throws InvalidArgumentException If the argument syntax is invalid or unmatched braces are encountered.
     * @throws OutOfBoundsException If the argument number, name, or type exceeds predefined limits.
     */
    private function parseArg(int $index, int $nestingLevel): int
    {// Mark the start of this argument and record a placeholder ArgType.
        $argStart = count($this->parts);
        $argType = ArgType::NONE;
        $this->addPart(Type::ARG_START, $index, 1, $this->argTypeOrdinal($argType));

        // Skip whitespace after '{' and capture the argument name/number span.
        $nameIndex = $index = $this->skipWhiteSpace($index + 1);
        if ($index === $this->msgLength) {
            throw new InvalidArgumentException("Unmatched '{' braces in message " . $this->prefix());
        }

        // Parse identifier characters, then determine if it is a numeric index or name.
        $index = $this->skipIdentifier($index);
        $number = $this->parseArgNumber($nameIndex, $index);
        $length = $index - $nameIndex;

        // Validate and record an ARG_NUMBER or ARG_NAME part.
        if ($number >= 0) {
            $this->hasArgNumbers = true;
            $this->addPart(Type::ARG_NUMBER, $nameIndex, $length, $number);
        } elseif ($number === self::ARG_NAME_NOT_NUMBER) {
            if ($length > Part::MAX_LENGTH) {
                throw new OutOfBoundsException("Argument name too long: " . $this->prefix($nameIndex));
            }
            $this->hasArgNames = true;
            $this->addPart(Type::ARG_NAME, $nameIndex, $length, 0);
        } elseif ($number === self::ARG_VALUE_OVERFLOW) {
            throw new OutOfBoundsException("Argument number too large: " . $this->prefix($nameIndex));
        } else {
            throw new InvalidArgumentException("Bad argument syntax: " . $this->prefix($nameIndex));
        }

        // After name/number, expect either '}' or ',' for type/style.
        $index = $this->skipWhiteSpace($index);
        if ($index === $this->msgLength) {
            throw new InvalidArgumentException("Unmatched '{' braces in message " . $this->prefix());
        }

        $c = $this->charAt($index);
        if ($c !== '}') {
            // Must have a comma introducing the argument type.
            if ($c !== ',') {
                throw new InvalidArgumentException("Bad argument syntax: " . $this->prefix($nameIndex));
            }

            // Read the type token (e.g., "number", "plural", "select").
            $typeIndex = $index = $this->skipWhiteSpace($index + 1);
            while ($index < $this->msgLength && $this->isArgTypeChar($this->charAt($index))) {
                $index++;
            }
            $length = $index - $typeIndex;

            // Validate that the type is followed by ',' or '}'.
            $index = $this->skipWhiteSpace($index);
            if ($index === $this->msgLength) {
                throw new InvalidArgumentException("Unmatched '{' braces in message " . $this->prefix());
            }
            $c = $this->charAt($index);
            if ($length === 0 || ($c !== ',' && $c !== '}')) {
                throw new InvalidArgumentException("Bad argument syntax: " . $this->prefix($nameIndex));
            }
            if ($length > Part::MAX_LENGTH) {
                throw new OutOfBoundsException("Argument type name too long: " . $this->prefix($nameIndex));
            }

            // Map the type token to a known ArgType.
            $argType = ArgType::SIMPLE;
            if ($length === 6) {
                if ($this->isChoice($typeIndex)) {
                    $argType = ArgType::CHOICE;
                } elseif ($this->isPlural($typeIndex)) {
                    $argType = ArgType::PLURAL;
                } elseif ($this->isSelect($typeIndex)) {
                    $argType = ArgType::SELECT;
                }
            } elseif ($length === 13) {
                if ($this->isSelect($typeIndex) && $this->isOrdinal($typeIndex + 6)) {
                    $argType = ArgType::SELECTORDINAL;
                }
            }

            // Update the ARG_START part to carry the resolved ArgType.
            $this->replaceLastArgStartValue($argStart, $this->argTypeOrdinal($argType));
            if ($argType === ArgType::SIMPLE) {
                $this->addPart(Type::ARG_TYPE, $typeIndex, $length, 0);
            }

            // If there's no style part, only SIMPLE args are allowed.
            if ($c === '}') {
                if ($argType !== ArgType::SIMPLE) {
                    throw new InvalidArgumentException("No style field for complex argument: " . $this->prefix($nameIndex));
                }
            } else {
                // Parse the style body depending on the argument type.
                $index++;
                if ($argType === ArgType::SIMPLE) {
                    $index = $this->parseSimpleStyle($index);
                } elseif ($argType === ArgType::CHOICE) {
                    $index = $this->parseChoiceStyleInternal($index, $nestingLevel);
                } else {
                    $index = $this->parsePluralOrSelectStyle($argType, $index, $nestingLevel);
                }
            }
        }

        // Close the argument and return the index right after the closing '}'.
        $this->addLimitPart($argStart, Type::ARG_LIMIT, $index, 1, $this->argTypeOrdinal($argType));
        return $index + 1;
    }

    /**
     * Parses a simple style format within a message pattern and records it as an argument style part.
     *
     * @param int $index The starting index of the style text in the message pattern.
     * @return int The index immediately following the parsed style text.
     * @throws InvalidArgumentException If quoted literal text is unterminated, if braces are unmatched,
     *                                  or if a syntax error occurs in the message pattern.
     * @throws OutOfBoundsException If the style text length exceeds the maximum allowed limit.
     */
    private function parseSimpleStyle(int $index): int
    {
        // Remember where the style text starts.
        $start = $index;
        // Track nested braces inside the style.
        $nestedBraces = 0;
        // Cache total message length for loop bounds.
        $length = $this->msgLength;
        while ($index < $length) {
            // Read the next character and advance the cursor.
            $c = $this->charAt($index++);
            if ($c === "'") {
                // Skip over quoted literal text using pre-split chars array.
                $found = false;
                for ($i = $index; $i < $length; $i++) {
                    if ($this->chars[$i] === "'") {
                        $index = $i + 1;
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    // Unterminated quote is an error.
                    throw new InvalidArgumentException(
                        "Quoted literal argument style text reaches to the end of the message: " . $this->prefix($start)
                    );
                }
            } elseif ($c === '{') {
                // Enter a nested brace.
                $nestedBraces++;
            } elseif ($c === '}') {
                if ($nestedBraces > 0) {
                    // Close a nested brace.
                    $nestedBraces--;
                } else {
                    // Found the end of the simple style.
                    $len = --$index - $start;
                    if ($len > Part::MAX_LENGTH) {
                        // Style segment too long.
                        throw new OutOfBoundsException("Argument style text too long: " . $this->prefix($start));
                    }
                    // Record the ARG_STYLE part and return the end index.
                    $this->addPart(Type::ARG_STYLE, $start, $len, 0);
                    return $index;
                }
            }
        }
        // Reached end without a matching closing brace.
        throw new InvalidArgumentException("Unmatched '{' braces in message " . $this->prefix());
    }

    /**
     * Parses an internal representation of a ChoiceFormat pattern.
     *
     * @param int $index The starting index within the pattern to begin parsing.
     * @param int $nestingLevel Current level of nesting in the pattern.
     * @return int The updated index after parsing the choice style segment.
     * @throws InvalidArgumentException If the pattern has syntax errors, missing segments,
     *                                  or invalid choice separators.
     * @throws OutOfBoundsException If numeric selectors exceed allowable length limits.
     */
    private function parseChoiceStyleInternal(int $index, int $nestingLevel): int
    {
        $start = $index;
        $index = $this->skipWhiteSpace($index);
        $length = $this->msgLength;

        // Ensure there is a choice pattern to parse (not end or immediate '}').
        if ($index === $length || $this->charAt($index) === '}') {
            throw new InvalidArgumentException("Missing choice argument pattern in " . $this->prefix());
        }

        while (true) {
            // Parse the numeric selector token.
            $numberIndex = $index;
            $index = $this->skipDouble($index);
            $len = $index - $numberIndex;

            // Reject empty or overlong numeric selectors.
            if ($len === 0) {
                throw new InvalidArgumentException("Bad choice pattern syntax: " . $this->prefix($start));
            }
            if ($len > Part::MAX_LENGTH) {
                throw new OutOfBoundsException("Choice number too long: " . $this->prefix($numberIndex));
            }

            // Validate and record the numeric selector value.
            $this->parseDouble($numberIndex, $index, true);

            // Expect a choice separator after optional whitespace.
            $index = $this->skipWhiteSpace($index);
            if ($index === $length) {
                throw new InvalidArgumentException("Bad choice pattern syntax: " . $this->prefix($start));
            }

            // Separator must be one of #, <, or ≤.
            $c = $this->charAt($index);
            if (!($c === '#' || $c === '<' || $this->startsWithAt("≤", $index))) {
                throw new InvalidArgumentException(
                    "Expected choice separator (#<≤) instead of '$c' in choice pattern " . $this->prefix($start)
                );
            }

            // Record the selector token, then parse the following message fragment.
            $this->addPart(Type::ARG_SELECTOR, $index, 1, 0);
            $index = $this->parseMessage($index + 1, 0, $nestingLevel + 1, ArgType::CHOICE);

            // If we hit the end, parsing is complete.
            if ($index === $length) {
                return $index;
            }

            // If terminated by '}', verify nesting and finish this choice style.
            if ($this->charAt($index) === '}') {
                if (!$this->inMessageFormatPattern($nestingLevel)) {
                    throw new InvalidArgumentException("Bad choice pattern syntax: " . $this->prefix($start));
                }
                return $index;
            }

            // Otherwise skip over '|' (implicit) and continue with the next choice segment.
            $index = $this->skipWhiteSpace($index + 1);
        }
    }

    /**
     * Parses a pattern for "plural" or "select" argument styles, ensuring proper syntax, selector validation,
     * and message fragment parsing. Responsible for handling nesting, required keywords, and selectors syntax.
     *
     * @param ArgType $argType The type of argument style being parsed, such as plural or select.
     * @param int $index The current index in the pattern string where parsing starts.
     * @param int $nestingLevel The level of nesting within the message format pattern.
     * @return int The updated index position after parsing the plural or select style.
     * @throws InvalidArgumentException If the syntax of the provided plural/select pattern is invalid or if required
     *                                  elements (e.g., the "other" case) are missing.
     * @throws OutOfBoundsException If a selector or offset exceeds allowed length constraints.
     */
    private function parsePluralOrSelectStyle(ArgType $argType, int $index, int $nestingLevel): int
    {
        $start = $index;                 // remember the start position for the error context
        $isEmpty = true;                 // true until a selector/message pair is parsed
        $hasOther = false;               // track the required "other" selector
        $length = $this->msgLength;

        while (true) {
            $index = $this->skipWhiteSpace($index); // skip leading whitespace
            $eos = $index === $length;

            // end of style: '}' or end of string
            if ($eos || $this->charAt($index) === '}') {
                // validate matching end depending on nesting context
                if ($eos === $this->inMessageFormatPattern($nestingLevel)) {
                    throw new InvalidArgumentException(
                        "Bad " . strtolower($argType->name) . " pattern syntax: " . $this->prefix($start)
                    );
                }
                // plural/select requires an "other" case
                if (!$hasOther) {
                    throw new InvalidArgumentException(
                        "Missing 'other' keyword in " . strtolower($argType->name) . " pattern in " . $this->prefix()
                    );
                }
                return $index;
            }

            $selectorIndex = $index;

            // plural explicit-value selector: "=n"
            if ($argType->hasPluralStyle() && $this->charAt($selectorIndex) === '=') {
                $index = $this->skipDouble($index + 1);
                $len = $index - $selectorIndex;
                if ($len === 1) {
                    throw new InvalidArgumentException(
                        "Bad " . strtolower($argType->name) . " pattern syntax: " . $this->prefix($start)
                    );
                }
                if ($len > Part::MAX_LENGTH) {
                    throw new OutOfBoundsException("Argument selector too long: " . $this->prefix($selectorIndex));
                }
                $this->addPart(Type::ARG_SELECTOR, $selectorIndex, $len, 0);
                $this->parseDouble($selectorIndex + 1, $index, false); // store numeric selector value
            } else {
                // keyword selector (e.g., one, few, other, male)
                $index = $this->skipIdentifier($index);
                $len = $index - $selectorIndex;

                if ($len === 0) {
                    throw new InvalidArgumentException(
                        "Bad " . strtolower($argType->name) . " pattern syntax: " . $this->prefix($start)
                    );
                }

                // plural "offset:" must be first and only once
                if ($argType->hasPluralStyle() && $len === 6 && $index < $length && $this->startsWithAt('offset:', $selectorIndex)) {
                    if (!$isEmpty) {
                        throw new InvalidArgumentException(
                            "Plural argument 'offset:' (if present) must precede key-message pairs: " . $this->prefix($start)
                        );
                    }
                    $valueIndex = $this->skipWhiteSpace($index + 1);
                    $index = $this->skipDouble($valueIndex);
                    if ($index === $valueIndex) {
                        throw new InvalidArgumentException("Missing value for plural 'offset:' " . $this->prefix($start));
                    }
                    if (($index - $valueIndex) > Part::MAX_LENGTH) {
                        throw new OutOfBoundsException("Plural offset value too long: " . $this->prefix($valueIndex));
                    }
                    $this->parseDouble($valueIndex, $index, false); // store offset value
                    $isEmpty = false;
                    continue; // offset doesn't consume a message fragment
                }

                if ($len > Part::MAX_LENGTH) {
                    throw new OutOfBoundsException("Argument selector too long: " . $this->prefix($selectorIndex));
                }
                $this->addPart(Type::ARG_SELECTOR, $selectorIndex, $len, 0);

                // mark if the selector is "other"
                if (mb_substr($this->msg, $selectorIndex, $len) === 'other') {
                    $hasOther = true;
                }
            }

            // "{message}" must follow each selector
            $index = $this->skipWhiteSpace($index);
            if ($index === $length || $this->charAt($index) !== '{') {
                throw new InvalidArgumentException(
                    "No message fragment after " . strtolower($argType->name) . " selector: " . $this->prefix($selectorIndex)
                );
            }

            // parse nested message fragment
            $index = $this->parseMessage($index, 1, $nestingLevel + 1, $argType);
            $isEmpty = false;
        }
    }

    /**
     * Validates and parses an argument number from this pattern.
     * @param int $start Start index.
     * @param int $limit Limit index.
     * @return int >=0 if number, ARG_NAME_NOT_NUMBER, or ARG_NAME_NOT_VALID otherwise.
     */
    private function parseArgNumber(int $start, int $limit): int
    {
        return self::parseArgNumberFromString($this->msg, $start, $limit);
    }

    /**
     * Parses a numeric argument from a substring and returns its integer value.
     *
     * @param string $s The string containing the numeric argument to be parsed.
     * @param int $start The starting index of the substring to be parsed.
     * @param int $limit The ending index (exclusive) of the substring to be parsed.
     * @return int The parsed integer value if valid, or a predefined constant indicating an error:
     *             - `ARG_NAME_NOT_VALID` if the parsed value is invalid.
     *             - `ARG_NAME_NOT_NUMBER` if the substring does not represent a numeric value.
     */
    private static function parseArgNumberFromString(string $s, int $start, int $limit): int
    {
        // Reject empty range.
        if ($start >= $limit) {
            return self::ARG_NAME_NOT_VALID;
        }

        // Read the first character and decide how to start parsing.
        $c = $s[$start++];
        if ($c === '0') {
            // "0" alone is valid; a leading zero with more digits is invalid.
            if ($start === $limit) {
                return 0;
            }
            return self::ARG_NAME_NOT_VALID;
        } elseif (($ord = mb_ord($c)) >= 0x31 /* ord('1') */ && $ord <= 0x39 /* ord('9') */) {
            /* 0x30 === ord('0') */
            $number = $ord - 0x30;
        } else {
            // Non-digit start means “not a number”.
            return self::ARG_NAME_NOT_NUMBER;
        }

        // Parse remaining digits, rejecting any non-digit.
        while ($start < $limit) {
            $c = $s[$start++];
            if (($ord = mb_ord($c)) >= 0x30 /* ord('0') */ && $ord <= 0x39 /* ord('9') */) {
                // Mark as invalid if it would overflow when extended.
                if ($number >= intdiv(Part::MAX_VALUE, 10)) {
                    return self::ARG_VALUE_OVERFLOW;
                }
                $number = $number * 10 + ($ord - 0x30 /* ord('0') */);
            } else {
                return self::ARG_NAME_NOT_NUMBER;
            }
        }

        // Return number if valid, otherwise the invalid marker.
        return $number;
    }

    /**
     * Parses a numeric value within a given range of indices in a message string.
     * Determines whether the value is integral, floating-point, or infinity, and handles it accordingly.
     *
     * @param int $start The starting index of the numeric value in the message string.
     * @param int $limit The end index (exclusive) of the numeric value in the message string.
     * @param bool $allowInfinity Indicates whether the numeric value can represent infinity.
     *
     * @return void
     * @throws InvalidArgumentException If the syntax for the numeric value is invalid.
     */
    private function parseDouble(int $start, int $limit, bool $allowInfinity): void
    {
        // Validate bounds: there must be at least one character to parse.
        if ($start >= $limit) {
            throw new InvalidArgumentException("Bad syntax for numeric value.");
        }

        $index = $start;
        $isNegative = 0;
        $c = $this->charAt($index++);

        // Handle optional leading sign.
        if ($c === '-') {
            $isNegative = 1;
            if ($index === $limit) {
                throw new InvalidArgumentException("Bad syntax for numeric value.");
            }
            $c = $this->charAt($index++);
        } elseif ($c === '+') {
            if ($index === $limit) {
                throw new InvalidArgumentException("Bad syntax for numeric value.");
            }
            $c = $this->charAt($index++);
        }

        // Special-case infinity symbol; only valid if allowed and consumes the whole token.
        if ($this->startsWithAt("∞", $index - 1 /* mb_strlen("∞") */)) {
            if ($allowInfinity && $index === $limit) {
                $value = $isNegative ? -INF : INF;
                $this->addArgDoublePart($value, $start, $limit - $start);
                return;
            }
            throw new InvalidArgumentException("Bad syntax for numeric value.");
        }

        // Fast-path: parse integer digits and keep within max storable int range.
        $value = 0;
        $ord = ord($c);
        while ($ord >= 0x30 /* ord('0') */ && $ord <= 0x39 /* ord('9') */) {
            $value = $value * 10 + ($ord - 0x30 /* ord('0') */);
            if ($value > (Part::MAX_VALUE + $isNegative)) {
                break;
            }
            // If we consumed all chars, store as integer part and finish.
            if ($index === $limit) {
                $this->addPart(Type::ARG_INT, $start, $limit - $start, $isNegative ? -$value : $value);
                return;
            }
            $ord = ord($this->charAt($index++));
        }

        // Fallback: parse as float (handles decimals, exponent, overflow).
        // Optimized to use the pre-split chars array.
        $length = $limit - $start;
        $numericValue = (float)implode(array_slice($this->chars, $start, $length));
        $this->addArgDoublePart($numericValue, $start, $limit - $start);
    }

    /**
     * Skips over consecutive whitespace characters in a string starting from the given index.
     * @param int $index The starting position in the string to begin skipping whitespace.
     * @return int The updated index positioned immediately after the last skipped whitespace character.
     */
    private function skipWhiteSpace(int $index): int
    {
        $length = $this->msgLength;
        while ($index < $length && preg_match('#\G[' . self::Pattern_White_Space . ']#xu', $this->msg, $m, 0, $index)) {
            $index += mb_strlen($m[0]);
        }
        return $index;
    }

    /**
     * Skips over an identifier in the message starting at the given index.
     * An identifier is defined as a sequence of characters excluding punctuation and whitespace.
     *
     * @param int $index The starting position in the message from which to skip the identifier.
     * @return int The new position in the message after skipping the identifier.
     */
    private function skipIdentifier(int $index): int
    {
        // ICU Pattern_Syntax + Pattern_White_Space (exact, hard-coded)
        if (preg_match('#\G[^' . self::Pattern_White_Space . self::Pattern_Identifier . ']+#xu', $this->msg, $m, 0, $index)) {
            return $index + mb_strlen($m[0]);
        }
        return $index;
    }

    /**
     * Skips over a sequence of characters representing a numeric value, starting at the given index,
     * and returns the index of the first character that does not match numeric patterns.
     *
     * @param int $index The starting index from which to begin analyzing the character sequence.
     * @return int The index of the first character that does not match numeric patterns.
     */
    private function skipDouble(int $index): int
    {
        $length = $this->msgLength; // Cache message length for bounds.
        while ($index < $length) { // Scan forward from the given index.
            $c = $this->charAt($index); // Current character.
            if (
                // Stop if not a number-sign/decimal char OR, not digit/exponent/infinity.
                (mb_ord($c) < 0x30 /* ord('0') */ && !str_contains("+-.", $c)) ||
                (mb_ord($c) > 0x39 /* ord('9') */ && $c !== 'e' && $c !== 'E' && !$this->startsWithAt("∞", $index))
            ) {
                break; // End of numeric token.
            }
            $index++; // Advance while the numeric token continues.
        }
        return $index; // Return index just after the numeric token.
    }

    /**
     * @return bool true if we are inside a MessageFormat (sub-)pattern,
     *         as opposed to inside a top-level choice/plural/select pattern.
     */
    private function inMessageFormatPattern(int $nestingLevel): bool
    {
        return $nestingLevel > 0 || (isset($this->parts[0]) && $this->parts[0]->getType() === Type::MSG_START);
    }

    /**
     * @return bool true if we are in a MessageFormat sub-pattern
     *         of a top-level ChoiceFormat pattern.
     */
    private function inTopLevelChoiceMessage(int $nestingLevel, ArgType $parentType): bool
    {
        return $nestingLevel === 1 && $parentType === ArgType::CHOICE && ($this->parts[0]->getType() ?? null) !== Type::MSG_START;
    }

    /**
     * Adds a Part to the list.
     */
    private function addPart(Type $type, int $index, int $length, int $value): void
    {
        $this->parts[] = new Part($type, $index, $length, $value);
    }

    /**
     * Adds a limit Part and ties it to its start part.
     */
    private function addLimitPart(int $start, Type $type, int $index, int $length, int $value): void
    {
        $this->limitPartIndexes[$start] = count($this->parts);
        $this->addPart($type, $index, $length, $value);
    }

    /**
     * Adds a numeric double Part.
     * @throws OutOfBoundsException if too many numeric values are present.
     */
    private function addArgDoublePart(float $numericValue, int $start, int $length): void
    {
        $numericIndex = count($this->numericValues);
        if ($numericIndex > Part::MAX_VALUE) {
            // @codeCoverageIgnoreStart
            throw new OutOfBoundsException("Too many numeric values");
            // @codeCoverageIgnoreEnd
        }
        $this->numericValues[] = $numericValue;
        $this->addPart(Type::ARG_DOUBLE, $start, $length, $numericIndex);
    }

    /**
     * Tests whether a string is a "pattern identifier".
     */
    private static function isIdentifier(string $s): bool
    {
        return (bool)preg_match('/^[^' . self::Pattern_White_Space . self::Pattern_Identifier . ']+$/u', $s);
    }

    /**
     * Tests whether a character is valid for an argument type identifier.
     */
    private function isArgTypeChar(?string $c): bool
    {
        if (empty($c)) {
            return false;
        }

        // Returns true if the provided character is an alphabetic letter (A–Z / a–z).
        // mb_ord()/mb_chr() normalizes the input to a single ASCII character before the check.
        return ctype_alpha($c);
    }

    /**
     * Tests whether the argument type string is "choice" (case-insensitive).
     */
    private function isChoice(int $index): bool
    {
        return $this->startsWithAt('choice', $index) || $this->startsWithAt('CHOICE', $index);
    }

    /**
     * Tests whether the argument type string is "plural" (case-insensitive).
     */
    private function isPlural(int $index): bool
    {
        return $this->startsWithAt('plural', $index) || $this->startsWithAt('PLURAL', $index);
    }

    /**
     * Tests whether the argument type string is "select" (case-insensitive).
     */
    private function isSelect(int $index): bool
    {
        return $this->startsWithAt('select', $index) || $this->startsWithAt('SELECT', $index);
    }

    /**
     * Tests whether the argument type suffix is "ordinal" (case-insensitive).
     */
    private function isOrdinal(int $index): bool
    {
        return $this->startsWithAt('ordinal', $index) || $this->startsWithAt('ORDINAL', $index);
    }

    /**
     * Generates a preview of the message text starting from a specified index.
     * Optionally includes position information in the preview when the starting index is not zero.
     *
     * @param int|null $start The starting index of the message slice. Defaults to the beginning of the message if null.
     * @return string A quoted preview of the message text, truncated with an ellipsis if it exceeds the maximum length.
     */
    private function prefix(?int $start = null): string
    {
        // Max length of the previewed message slice.
        $max = 24;
        // Work on the current message text.
        $s = $this->msg;
        // Default to the start of the message if no index provided.
        $start = $start ?? 0;

        // Build a prefix that includes position info when not at index 0.
        $prefix = $start === 0 ? '"' : '[at pattern index ' . $start . '] "';
        // Extract the message substring from the starting index.
        $substring = mb_substr($s, $start);

        // If the remaining text fits, return it quoted as-is.
        if (mb_strlen($substring) <= $max) {
            return $prefix . $substring . '"';
        }

        // Otherwise, return a truncated preview with an ellipsis.
        return $prefix . mb_substr($s, $start, $max - 4) . ' ..."';
    }

    /**
     * Returns the character at the given index.
     */
    private function charAt(int $index): ?string
    {
        return $this->chars[$index] ?? null;
    }

    /**
     * Returns true if the pattern starts with the given string at index.
     * Optimized for speed by leveraging the pre-split chars array and
     * avoiding expensive string construction or regex calls.
     *
     * @param string $needle The string to look for at the start of the pattern.
     * @param int $index The index at which to begin searching for the string.
     * @return bool true if the pattern starts with the given string at the given index.
     */
    private function startsWithAt(string $needle, int $index): bool
    {
        // Calculate length once. For the short constants used in this
        // parser, mb_strlen is very fast.
        $needleLen = mb_strlen($needle);

        if ($index + $needleLen > $this->msgLength) {
            return false;
        }

        // If the needle is a single character (very common in this parser),
        // perform a direct comparison.
        if ($needleLen === 1) {
            return $this->chars[$index] === $needle;
        }

        return mb_substr($this->msg, $index, $needleLen) === $needle;
    }

    /**
     * Determines the ordinal position of the given argument type within the cases of the ArgType enumeration.
     * @param ArgType $argType The argument type to look for within the enumeration cases.
     * @return int The ordinal position of the specified argument type, or 0 if it is not found.
     */
    private function argTypeOrdinal(ArgType $argType): int
    {
        $cases = ArgType::cases();
        foreach ($cases as $i => $case) {
            if ($case === $argType) {
                return $i;
            }
        }
        return 0;
    }

    /**
     * Replaces the start value of the last argument part at the given index with a new value.
     *
     * @param int $startIndex The index of the part to be updated.
     * @param int $newValue The new start value to set for the specified part.
     * @return void
     */
    private function replaceLastArgStartValue(int $startIndex, int $newValue): void
    {
        $part = $this->parts[$startIndex] ?? null;
        if ($part === null) {
            return;
        }
        $this->parts[$startIndex] = new Part($part->getType(), $part->getIndex(), $part->getLength(), $newValue);
    }


    /**
     * Iterator Interface implementation.
     */

    /**
     * Tracks the current position for iteration.
     *
     * @var int
     */
    private int $position;

    /**
     * Returns the current header field in the iteration.
     *
     * @return Part The current header field.
     */
    public function current(): Part
    {
        return $this->parts[$this->position];
    }

    /**
     * Returns the key of the current header field in the iteration.
     *
     * @return int The key of the current header field.
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * Moves the iterator to the next header field.
     *
     * @return void
     */
    public function next(): void
    {
        ++$this->position;
    }

    /**
     * Rewinds the iterator to the first header field.
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Checks if the current position is valid in the iteration.
     *
     * @return bool True if the current position is valid, false otherwise.
     */
    public function valid(): bool
    {
        return isset($this->parts[$this->position]);
    }


}