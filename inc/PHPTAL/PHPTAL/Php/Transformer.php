<?php
/**
 * PHPTAL templating engine
 *
 * PHP Version 5
 *
 * @category HTML
 * @package  PHPTAL
 * @author   Laurent Bedubourg <lbedubourg@motion-twin.com>
 * @author   Kornel Lesiński <kornel@aardvarkmedia.co.uk>
 * @license  http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @version  SVN: $Id$
 * @link     http://phptal.org/
 */

/**
 * Tranform php: expressions into their php equivalent.
 *
 * This transformer produce php code for expressions like :
 *
 * - a.b["key"].c().someVar[10].foo()
 * - (a or b) and (c or d)
 * - not myBool
 * - ...
 *
 * The $prefix variable may be changed to change the context lookup.
 *
 * example:
 *
 *      $res = PHPTAL_Php_Transformer::transform('a.b.c[x]', '$ctx->');
 *      $res == '$ctx->a->b->c[$ctx->x]';
 *
 * @package PHPTAL
 * @subpackage Php
 * @author Laurent Bedubourg <lbedubourg@motion-twin.com>
 */
class PHPTAL_Php_Transformer
{
    const ST_WHITE  = -1; // start of string or whitespace
    const ST_NONE   = 0;  // pass through (operators, parens, etc.)
    const ST_STR    = 1;  // 'foo'
    const ST_ESTR   = 2;  // "foo ${x} bar"
    const ST_VAR    = 3;  // abcd
    const ST_NUM    = 4;  // 123.02
    const ST_EVAL   = 5;  // $somevar
    const ST_MEMBER = 6;  // abcd.x
    const ST_STATIC = 7;  // class::[$]static|const
    const ST_DEFINE = 8;  // @MY_DEFINE

    /**
     * transform PHPTAL's php-like syntax into real PHP
     */
    public static function transform($str, $prefix='$')
    {
        $len = strlen($str);
        $state = self::ST_WHITE;
        $result = '';
        $i = 0;
        $inString = false;
        $backslashed = false;
        $instanceof = false;
        $eval = false;


        for ($i = 0; $i <= $len; $i++) {
            if ($i == $len) $c = "\0";
            else $c = $str[$i];

            switch ($state) {

                // after whitespace a variable-variable may start, ${var} → $ctx->{$ctx->var}
                case self::ST_WHITE:
                    if ($c === '$' && $i+1 < $len && $str[$i+1] === '{')
                    {
                        $result .= $prefix;
                        $state = self::ST_NONE;
                        continue;
                    }
                    /* NO BREAK - ST_WHITE is almost the same as ST_NONE */

                // no specific state defined, just eat char and see what to do with it.
                case self::ST_NONE:
                    // begin of eval without {
                    if ($c === '$' && $i+1 < $len && self::isAlpha($str[$i+1])) {
                        $state = self::ST_EVAL;
                        $mark = $i+1;
                        $result .= $prefix.'{';
                    }
                    elseif (self::isDigit($c))
                    {
                        $state = self::ST_NUM;
                        $mark = $i;
                    }
                    // that an alphabetic char, then it should be the begining
                    // of a var or static
                    // && !self::isDigit($c) checked earlier
                    elseif (self::isVarNameChar($c)) {
                        $state = self::ST_VAR;
                        $mark = $i;
                    }
                    // begining of double quoted string
                    elseif ($c === '"') {
                        $state = self::ST_ESTR;
                        $mark = $i;
                        $inString = true;
                    }
                    // begining of single quoted string
                    elseif ($c === '\'') {
                        $state = self::ST_STR;
                        $mark = $i;
                        $inString = true;
                    }
                    // closing a method, an array access or an evaluation
                    elseif ($c === ')' || $c === ']' || $c === '}') {
                        $result .= $c;
                        // if next char is dot then an object member must
                        // follow
                        if ($i+1 < $len && $str[$i+1] === '.') {
                            $result .= '->';
                            $state = self::ST_MEMBER;
                            $mark = $i+2;
                            $i+=2;
                        }
                    }
                    // @ is an access to some defined variable
                    elseif ($c === '@') {
                        $state = self::ST_DEFINE;
                        $mark = $i+1;
                    }
                    elseif (ctype_space($c)) {
                        $state = self::ST_WHITE;
                        $result .= $c;
                    }
                    // character we don't mind about
                    else {
                        $result .= $c;
                    }
                    break;

                // $xxx
                case self::ST_EVAL:
                    if (!self::isVarNameChar($c)) {
                        $result .= $prefix . substr($str, $mark, $i-$mark);
                        $result .= '}';
                        $state = self::ST_NONE;
                    }
                    break;

                // single quoted string
                case self::ST_STR:
                    if ($c === '\\') {
                        $backslashed = true;
                    } elseif ($backslashed) {
                        $backslashed = false;
                    }
                    // end of string, back to none state
                    elseif ($c === '\'') {
                        $result .= substr($str, $mark, $i-$mark+1);
                        $inString = false;
                        $state = self::ST_NONE;
                    }
                    break;

                // double quoted string
                case self::ST_ESTR:
                    if ($c === '\\') {
                        $backslashed = true;
                    } elseif ($backslashed) {
                        $backslashed = false;
                    }
                    // end of string, back to none state
                    elseif ($c === '"') {
                        $result .= substr($str, $mark, $i-$mark+1);
                        $inString = false;
                        $state = self::ST_NONE;
                    }
                    // instring interpolation, search } and transform the
                    // interpollation to insert it into the string
                    elseif ($c === '$' && $i+1 < $len && $str[$i+1] === '{') {
                        $result .= substr($str, $mark, $i-$mark) . '{';

                        $sub = 0;
                        for ($j = $i; $j<$len; $j++) {
                            if ($str[$j] === '{') {
                                $sub++;
                            } elseif ($str[$j] === '}' && (--$sub) == 0) {
                                $part = substr($str, $i+2, $j-$i-2);
                                $result .= self::transform($part, $prefix);
                                $i = $j;
                                $mark = $i;
                            }
                        }
                    }
                    break;

                // var state
                case self::ST_VAR:
                    if (self::isVarNameChar($c)) {
                    }
                    // end of var, begin of member (method or var)
                    elseif ($c === '.') {
                        $result .= $prefix . substr($str, $mark, $i-$mark);
                        $result .= '->';
                        $state = self::ST_MEMBER;
                        $mark = $i+1;
                    }
                    // static call, the var is a class name
                    elseif ($c === ':' && $i+1 < $len && $str[$i+1] === ':') {
                        $result .= substr($str, $mark, $i-$mark+1);
                        $mark = $i+1;
                        $i++;
                        $state = self::ST_STATIC;
                        break;
                    }
                    // function invocation, the var is a function name
                    elseif ($c === '(') {
                        $result .= substr($str, $mark, $i-$mark+1);
                        $state = self::ST_NONE;
                    }
                    // array index, the var is done
                    elseif ($c === '[') {
                        if ($str[$mark]==='_') { // superglobal?
                            $result .= '$' . substr($str, $mark, $i-$mark+1);
                        } else {
                            $result .= $prefix . substr($str, $mark, $i-$mark+1);
                        }
                        $state = self::ST_NONE;
                    }
                    // end of var with non-var-name character, handle keywords
                    // and populate the var name
                    else {
                        $var = substr($str, $mark, $i-$mark);
                        $low = strtolower($var);
                        // boolean and null
                        if ($low === 'true' || $low === 'false' || $low === 'null') {
                            $result .= $var;
                        }
                        // lt, gt, ge, eq, ...
                        elseif (array_key_exists($low, self::$TranslationTable)) {
                            $result .= self::$TranslationTable[$low];
                        }
                        // instanceof keyword
                        elseif ($low === 'instanceof') {
                            $result .= $var;
                            $instanceof = true;
                        }
                        // previous was instanceof
                        elseif ($instanceof) {
                            // last was instanceof, this var is a class name
                            $result .= $var;
                            $instanceof = false;
                        }
                        // regular variable
                        else {
                            $result .= $prefix . $var;
                        }
                        $i--;
                        $state = self::ST_NONE;
                    }
                    break;

                // object member
                case self::ST_MEMBER:
                    if (self::isVarNameChar($c)) {
                    }
                    // eval mode ${foo}
                    elseif ($c === '$' && ($i >= $len-2 || $str[$i+1] !== '{')) {
                        $result .= '{' . $prefix;
                        $mark++;
                        $eval = true;
                    }
                    // x.${foo} x->{foo}
                    elseif ($c === '$') {
                        $mark++;
                    }
                    // end of var member var, begin of new member
                    elseif ($c === '.') {
                        $result .= substr($str, $mark, $i-$mark);
                        if ($eval) { $result .='}'; $eval = false; }
                        $result .= '->';
                        $mark = $i+1;
                        $state = self::ST_MEMBER;
                    }
                    // begin of static access
                    elseif ($c === ':') {
                        $result .= substr($str, $mark, $i-$mark+1);
                        if ($eval) { $result .='}'; $eval = false; }
                        $state = self::ST_STATIC;
                        break;
                    }
                    // the member is a method or an array
                    elseif ($c === '(' || $c === '[') {
                        $result .= substr($str, $mark, $i-$mark+1);
                        if ($eval) { $result .='}'; $eval = false; }
                        $state = self::ST_NONE;
                    }
                    // regular end of member, it is a var
                    else {
                        $var = substr($str, $mark, $i-$mark);
                        if ($var !== '' && !preg_match('/^[a-z][a-z0-9_\x7f-\xff]*$/i',$var)) {
                            throw new PHPTAL_ParserException("Invalid field name '$var' in expression php:$str");
                        }
                        $result .= $var;
                        if ($eval) { $result .='}'; $eval = false; }
                        $state = self::ST_NONE;
                        $i--;
                    }
                    break;

                // wait for separator
                case self::ST_DEFINE:
                    if (self::isVarNameChar($c)) {
                    } else {
                        $state = self::ST_NONE;
                        $result .= substr($str, $mark, $i-$mark);
                        $i--;
                    }
                    break;

                // static call, can be const, static var, static method
                // Klass::$static
                // Klass::const
                // Kclass::staticMethod()
                //
                case self::ST_STATIC:
                    if (self::isVarNameChar($c)) {
                    }
                    // static var
                    elseif ($c === '$') {
                    }
                    // end of static var which is an object and begin of member
                    elseif ($c === '.') {
                        $result .= substr($str, $mark, $i-$mark);
                        $result .= '->';
                        $mark = $i+1;
                        $state = self::ST_MEMBER;
                    }
                    // end of static var which is a class name
                    elseif ($c === ':') {
                        $result .= substr($str, $mark, $i-$mark+1);
                        $state = self::ST_STATIC;
                        break;
                    }
                    // static method or array
                    elseif ($c === '(' || $c === '[') {
                        $result .= substr($str, $mark, $i-$mark+1);
                        $state = self::ST_NONE;
                    }
                    // end of static var or const
                    else {
                        $result .= substr($str, $mark, $i-$mark);
                        $state = self::ST_NONE;
                        $i--;
                    }
                    break;

                // numeric value
                case self::ST_NUM:
                    if (!self::isDigitCompound($c)) {
                        $var = substr($str, $mark, $i-$mark);

                        if (self::isAlpha($c) || $c === '_') {
                            throw new PHPTAL_ParserException("Syntax error in number '$var$c' in expression php:$str");
                        }
                        if (!is_numeric($var)) {
                            throw new PHPTAL_ParserException("Syntax error in number '$var' in expression php:$str");
                        }

                        $result .= $var;
                        $state = self::ST_NONE;
                        $i--;
                    }
                    break;
            }
        }

        $result = trim($result);

        // CodeWriter doesn't like expressions that look like blocks
        if ($result[strlen($result)-1] === '}') return '('.$result.')';

        return $result;
    }

    private static function isAlpha($c)
    {
        $c = strtolower($c);
        return $c >= 'a' && $c <= 'z';
    }

    private static function isDigit($c)
    {
        return ($c >= '0' && $c <= '9');
    }

    private static function isDigitCompound($c)
    {
        return ($c >= '0' && $c <= '9' || $c === '.');
    }

    private static function isVarNameChar($c)
    {
        return self::isAlpha($c) || ($c >= '0' && $c <= '9') || $c === '_' || $c === '\\';
    }

    private static $TranslationTable = array(
        'not' => '!',
        'ne'  => '!=',
        'and' => '&&',
        'or'  => '||',
        'lt'  => '<',
        'gt'  => '>',
        'ge'  => '>=',
        'le'  => '<=',
        'eq'  => '==',
    );
}

