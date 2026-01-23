<?php
// tests/Utils/ICU/MessagePatternTest.php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Utils\ICU\ArgType;
use Utils\ICU\MessagePattern;
use Utils\ICU\Type;

/**
 * Tests the functionality of the `MessagePattern` class.
 *
 * @ref https://github.com/unicode-org/icu/blob/4ebbe0c828056017f47d2ba3b8e44d44367282c5/icu4j/samples/src/main/java/com/ibm/icu/samples/text/messagepattern/MessagePatternDemo.java
 *
 * This test suite validates various use cases of parsing and handling message patterns. Each test case ensures
 * that the `MessagePattern` behaves as expected for different styles such as empty patterns, named and numbered
 * arguments, choice style, plural style, and nested structures.
 *
 * The following key features are tested:
 *
 * - Parsing of empty message patterns.
 * - Validating argument names for correct formatting.
 * - Handling of simple message patterns with both named and numbered arguments.
 * - Parsing and validation of choice-style message patterns.
 * - Parsing and validating plural styles, including offsets and `REPLACE_NUMBER` constructs.
 * - Handling select-style and selectordinal-style patterns.
 * - Parsing and analyzing nested select and plural message patterns.
 * - Auto-quoting of apostrophes in message patterns.
 * - Choice-style parsing with special values such as infinity and less-than-or-equal operators.
 */
final class MessagePatternTest extends TestCase
{

    public function testParseEmpty()
    {
        $pattern = new MessagePattern();
        self::assertEquals(2, $pattern->parse('Hi')->countParts());
    }

    public function testParse()
    {
        $pattern = new MessagePattern();
        self::assertTrue($pattern->parse('Hi {0}')->countParts() > 2);
    }

    public function testValidateArgumentName(): void
    {
        self::assertSame(0, MessagePattern::validateArgumentName('0'));
        self::assertSame(12, MessagePattern::validateArgumentName('12'));

        self::assertSame(MessagePattern::ARG_NAME_NOT_VALID, MessagePattern::validateArgumentName('01'));
        self::assertSame(MessagePattern::ARG_NAME_NOT_NUMBER, MessagePattern::validateArgumentName('name'));
        self::assertSame(MessagePattern::ARG_NAME_NOT_VALID, MessagePattern::validateArgumentName('bad name'));
    }

    public function testParseSimpleNamedAndNumberedArgs(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('Hi {0} {name}');

        self::assertTrue($pattern->hasNumberedArguments());
        self::assertTrue($pattern->hasNamedArguments());

        self::assertSame(Type::MSG_START, $pattern->getPartType(0));
        self::assertSame(Type::MSG_LIMIT, $pattern->getPartType($pattern->countParts() - 1));

        $limit = $pattern->getLimitPartIndex(0);
        self::assertSame($pattern->countParts() - 1, $limit);

        $argNameFound = false;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType() === Type::ARG_NAME) {
                $argNameFound = true;
                self::assertSame('name', $pattern->getSubstring($part));
                break;
            }
        }

        self::assertTrue($argNameFound);
    }

    public function testParseChoiceStyle(): void
    {
        $pattern = new MessagePattern();
        $pattern->parseChoiceStyle('0#no|1#one|2#two');

        $countNumeric = 0;
        $countSelectors = 0;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType()->hasNumericValue()) {
                self::assertSame(floatval($countNumeric), $pattern->getNumericValue($part));
                $countNumeric++;
            }
            if ($part->getType() === Type::ARG_SELECTOR) {
                $countSelectors++;
            }
        }

        self::assertTrue($countSelectors === 3);
    }

    public function testParsePluralStyleAndOffset(): void
    {
        $pattern = new MessagePattern();
        $pattern->parsePluralStyle('offset:1 one{# item} other{# items}');

        self::assertSame(1.0, $pattern->getPluralOffset(0));

        $hasReplaceNumber = false;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            if ($pattern->getPartType($i) === Type::REPLACE_NUMBER) {
                $hasReplaceNumber = true;
                break;
            }
        }

        self::assertTrue($hasReplaceNumber);
    }

    public function testParseSelectStyle(): void
    {
        $pattern = new MessagePattern();
        $pattern->parseSelectStyle('male{He} female{She} other{They}');

        $hasOtherSelector = false;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType() === Type::ARG_SELECTOR && $pattern->getSubstring($part) === 'other') {
                $hasOtherSelector = true;
                break;
            }
        }

        self::assertTrue($hasOtherSelector);
    }

    public function testAutoQuoteApostropheDeep(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse("I don't {name}");

        self::assertSame("I don''t {name}", $pattern->autoQuoteApostropheDeep());
    }

    public function testParsePluralInMessageFormatPattern(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{count, plural, one{# file} other{# files}}');

        $argStartFound = false;
        $argType = null;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType() === Type::ARG_START) {
                $argStartFound = true;
                $argType = $part->getArgType();
                break;
            }
        }

        self::assertTrue($argStartFound);
        self::assertSame(ArgType::PLURAL, $argType);
    }

    public function testParseNestedSelectAndPlural(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{gender, select, female{{count, plural, one{# file} other{# files}}} other{No files}}');

        $hasSelect = false;
        $hasPlural = false;
        $hasReplaceNumber = false;

        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType() === Type::ARG_START) {
                if ($part->getArgType() === ArgType::SELECT) {
                    $hasSelect = true;
                }
                if ($part->getArgType() === ArgType::PLURAL) {
                    $hasPlural = true;
                }
            }
            if ($part->getType() === Type::REPLACE_NUMBER) {
                $hasReplaceNumber = true;
            }
        }

        self::assertTrue($hasSelect);
        self::assertTrue($hasPlural);
        self::assertTrue($hasReplaceNumber);
    }

    public function testParseSelectOrdinalStyle(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse('{pos, selectordinal, one{#st} two{#nd} few{#rd} other{#th}}');

        $hasSelectOrdinal = false;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType() === Type::ARG_START && $part->getArgType() === ArgType::SELECTORDINAL) {
                $hasSelectOrdinal = true;
                break;
            }
        }

        self::assertTrue($hasSelectOrdinal);
    }

    public function testParseChoiceStyleWithInfinityAndLeq(): void
    {
        $pattern = new MessagePattern();
        $pattern->parseChoiceStyle('0#none|1<single|∞≤many');

        $selectorCount = 0;
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            if ($pattern->getPartType($i) === Type::ARG_SELECTOR) {
                $selectorCount++;
            }
        }
        self::assertEquals(12, $pattern->countParts());
        self::assertSame(3, $selectorCount);

        /*
         * JAVA ICU object printed
         *
         * message: 0#none|1<single|∞≤many
         *
         * 0: ARG_INT(0)@0="0"=0.0
         * 1: ARG_SELECTOR(0)@1="#"
         * 2:   MSG_START(1)@2
         * 3:   MSG_LIMIT(1)@6="|"
         * 4: ARG_INT(1)@7="1"=1.0
         * 5: ARG_SELECTOR(0)@8="<"
         * 6:   MSG_START(1)@9
         * 7:   MSG_LIMIT(1)@15="|"
         * 8: ARG_DOUBLE(0)@16="∞"=Infinity
         * 9: ARG_SELECTOR(0)@17="≤"
         *10:   MSG_START(1)@18
         *11:   MSG_LIMIT(1)@22
         */

        $expected = [
            [Type::ARG_INT, 0, 1, 0, '0', 0.0],
            [Type::ARG_SELECTOR, 1, 1, 0, '#', null],
            [Type::MSG_START, 2, 0, 1, '', null],
            [Type::MSG_LIMIT, 6, 1, 1, '|', null],
            [Type::ARG_INT, 7, 1, 1, '1', 1.0],
            [Type::ARG_SELECTOR, 8, 1, 0, '<', null],
            [Type::MSG_START, 9, 0, 1, '', null],
            [Type::MSG_LIMIT, 15, 1, 1, '|', null],
            [Type::ARG_DOUBLE, 16, 1, 0, '∞', INF],
            [Type::ARG_SELECTOR, 17, 1, 0, '≤', null],
            [Type::MSG_START, 18, 0, 1, '', null],
            [Type::MSG_LIMIT, 22, 0, 1, '', null],
        ];

        self::assertSame(count($expected), $pattern->countParts());

        foreach ($pattern as $i => $part) {
            [$type, $index, $length, $value, $substring, $numeric] = $expected[$i];

            self::assertSame($type, $part->getType(), "Part #$i type");
            self::assertSame($index, $part->getIndex(), "Part #$i index");
            self::assertSame($length, $part->getLength(), "Part #$i length");
            self::assertSame($value, $part->getValue(), "Part #$i value");

            self::assertSame($substring, $pattern->getSubstring($part), "Part #$i substring");

            if ($numeric !== null) {
                self::assertSame($numeric, $pattern->getNumericValue($part), "Part #$i numeric");
            }
        }
    }

    public function testParseComplexQuotedPattern(): void
    {
        $pattern = new MessagePattern();
        $input = <<<'MSG'
I don't {a,plural,other{w'{'on't #'#'}} and {b,select,other{shan't'}'}} '{'''know'''}' and {c,choice,0#can't'|'}{z,number,#'#'###.00'}'}.
MSG;

        $pattern->parse($input);

        /*
         * Java ICU object printed
         * 0: MSG_START(0)@0
         * 1: INSERT_CHAR(39)@6
         * 2: ARG_START(PLURAL)@8="{"
         * 3: ARG_NAME(0)@9="a"
         * 4: ARG_SELECTOR(0)@18="other"
         * 5: MSG_START(1)@23="{"
         * 6: SKIP_SYNTAX(0)@25="'"
         * 7: SKIP_SYNTAX(0)@27="'"
         * 8: INSERT_CHAR(39)@31
         * 9: REPLACE_NUMBER(0)@33="#"
         * 10: SKIP_SYNTAX(0)@34="'"
         * 11: SKIP_SYNTAX(0)@36="'"
         * 12: MSG_LIMIT(1)@37="}"
         * 13: ARG_LIMIT(PLURAL)@38="}"
         * 14: ARG_START(SELECT)@44="{"
         * 15: ARG_NAME(0)@45="b"
         * 16: ARG_SELECTOR(0)@54="other"
         * 17: MSG_START(1)@59="{"
         * 18: INSERT_CHAR(39)@65
         * 19: SKIP_SYNTAX(0)@66="'"
         * 20: SKIP_SYNTAX(0)@68="'"
         * 21: MSG_LIMIT(1)@69="}"
         * 22: ARG_LIMIT(SELECT)@70="}"
         * 23: SKIP_SYNTAX(0)@72="'"
         * 24: SKIP_SYNTAX(0)@75="'"
         * 25: SKIP_SYNTAX(0)@76="'"
         * 26: SKIP_SYNTAX(0)@82="'"
         * 27: SKIP_SYNTAX(0)@83="'"
         * 28: SKIP_SYNTAX(0)@85="'"
         * 29: ARG_START(CHOICE)@91="{"
         * 30: ARG_NAME(0)@92="c"
         * 31: ARG_INT(0)@101="0"=0.0
         * 32: ARG_SELECTOR(0)@102="#"
         * 33: MSG_START(1)@103
         * 34: INSERT_CHAR(39)@107
         * 35: SKIP_SYNTAX(0)@108="'"
         * 36: SKIP_SYNTAX(0)@110="'"
         * 37: MSG_LIMIT(1)@111
         * 38: ARG_LIMIT(CHOICE)@111="}"
         * 39: ARG_START(SIMPLE)@112="{"
         * 40: ARG_NAME(0)@113="z"
         * 41: ARG_TYPE(0)@115="number"
         * 42: ARG_STYLE(0)@122="#'#'###.00'}'"
         * 43: ARG_LIMIT(SIMPLE)@135="}"
         * 44: MSG_LIMIT(0)@137
         */

        $expected = [
            [Type::MSG_START, 0, 0, 0, '', null, null],
            [Type::INSERT_CHAR, 6, 0, 39, '', null, null],
            [Type::ARG_START, 8, 1, 0, '{', null, ArgType::PLURAL],
            [Type::ARG_NAME, 9, 1, 0, 'a', null, null],
            [Type::ARG_SELECTOR, 18, 5, 0, 'other', null, null],
            [Type::MSG_START, 23, 1, 1, '{', null, null],
            [Type::SKIP_SYNTAX, 25, 1, 0, "'", null, null],
            [Type::SKIP_SYNTAX, 27, 1, 0, "'", null, null],
            [Type::INSERT_CHAR, 31, 0, 39, '', null, null],
            [Type::REPLACE_NUMBER, 33, 1, 0, '#', null, null],
            [Type::SKIP_SYNTAX, 34, 1, 0, "'", null, null],
            [Type::SKIP_SYNTAX, 36, 1, 0, "'", null, null],
            [Type::MSG_LIMIT, 37, 1, 1, '}', null, null],
            [Type::ARG_LIMIT, 38, 1, 0, '}', null, ArgType::PLURAL],
            [Type::ARG_START, 44, 1, 0, '{', null, ArgType::SELECT],
            [Type::ARG_NAME, 45, 1, 0, 'b', null, null],
            [Type::ARG_SELECTOR, 54, 5, 0, 'other', null, null],
            [Type::MSG_START, 59, 1, 1, '{', null, null],
            [Type::INSERT_CHAR, 65, 0, 39, '', null, null],
            [Type::SKIP_SYNTAX, 66, 1, 0, "'", null, null],
            [Type::SKIP_SYNTAX, 68, 1, 0, "'", null, null],
            [Type::MSG_LIMIT, 69, 1, 1, '}', null, null],
            [Type::ARG_LIMIT, 70, 1, 0, '}', null, ArgType::SELECT],
            [Type::SKIP_SYNTAX, 72, 1, 0, "'", null, null],
            [Type::SKIP_SYNTAX, 75, 1, 0, "'", null, null],
            [Type::SKIP_SYNTAX, 76, 1, 0, "'", null, null],
            [Type::SKIP_SYNTAX, 82, 1, 0, "'", null, null],
            [Type::SKIP_SYNTAX, 83, 1, 0, "'", null, null],
            [Type::SKIP_SYNTAX, 85, 1, 0, "'", null, null],
            [Type::ARG_START, 91, 1, 0, '{', null, ArgType::CHOICE],
            [Type::ARG_NAME, 92, 1, 0, 'c', null, null],
            [Type::ARG_INT, 101, 1, 0, '0', 0.0, null],
            [Type::ARG_SELECTOR, 102, 1, 0, '#', null, null],
            [Type::MSG_START, 103, 0, 1, '', null, null],
            [Type::INSERT_CHAR, 107, 0, 39, '', null, null],
            [Type::SKIP_SYNTAX, 108, 1, 0, "'", null, null],
            [Type::SKIP_SYNTAX, 110, 1, 0, "'", null, null],
            [Type::MSG_LIMIT, 111, 0, 1, '', null, null],
            [Type::ARG_LIMIT, 111, 1, 0, '}', null, ArgType::CHOICE],
            [Type::ARG_START, 112, 1, 0, '{', null, ArgType::SIMPLE],
            [Type::ARG_NAME, 113, 1, 0, 'z', null, null],
            [Type::ARG_TYPE, 115, 6, 0, 'number', null, null],
            [Type::ARG_STYLE, 122, 13, 0, "#'#'###.00'}'", null, null],
            [Type::ARG_LIMIT, 135, 1, 0, '}', null, ArgType::SIMPLE],
            [Type::MSG_LIMIT, 137, 0, 0, '', null, null],
        ];

        self::assertSame(count($expected), $pattern->countParts());

        foreach ($pattern as $i => $part) {
            [$type, $index, $length, $value, $substring, $numeric, $argType] = $expected[$i];

            self::assertSame($type, $part->getType(), "Part #$i type");
            self::assertSame($index, $part->getIndex(), "Part #$i index");
            self::assertSame($length, $part->getLength(), "Part #$i length");
            self::assertSame($substring, $pattern->getSubstring($part), "Part #$i substring");

            if ($argType !== null) {
                self::assertSame($argType, $part->getArgType(), "Part #$i argType");
            } else {
                self::assertSame($value, $part->getValue(), "Part #$i value");
            }

            if ($numeric !== null) {
                self::assertSame($numeric, $pattern->getNumericValue($part), "Part #$i numeric");
            }
        }
    }

    public function testParsePluralStyleWithExplicitSelector(): void
    {
        $pattern = new MessagePattern();
        $pattern->parsePluralStyle('=0{none} one{# item} other{# items}');

        $numericValues = [];
        for ($i = 0; $i < $pattern->countParts(); $i++) {
            $part = $pattern->getPart($i);
            if ($part->getType()->hasNumericValue()) {
                $numericValues[] = $pattern->getNumericValue($part);
            }
        }

        self::assertTrue(in_array(0.0, $numericValues, true));
    }

    public function testAutoQuoteApostropheWithQuotedLiterals(): void
    {
        $pattern = new MessagePattern();
        $pattern->parse("He said '{name}' and it's ok");

        self::assertSame("He said '{name}' and it''s ok", $pattern->autoQuoteApostropheDeep());
    }

}
