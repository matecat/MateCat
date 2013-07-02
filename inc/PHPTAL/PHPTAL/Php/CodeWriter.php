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
 * Helps generate php representation of a template.
 *
 * @package PHPTAL
 * @subpackage Php
 * @author Laurent Bedubourg <lbedubourg@motion-twin.com>
 */
class PHPTAL_Php_CodeWriter
{
    /**
     * max id of variable to give as temp
     */
    private $temp_var_counter=0;
    /**
     * stack with free'd variables
     */
    private $temp_recycling=array();

    /**
     * keeps track of seen functions for function_exists
     */
    private $known_functions = array();


    public function __construct(PHPTAL_Php_State $state)
    {
        $this->_state = $state;
    }

    public function createTempVariable()
    {
        if (count($this->temp_recycling)) return array_shift($this->temp_recycling);
        return '$_tmp_'.(++$this->temp_var_counter);
    }

    public function recycleTempVariable($var)
    {
        if (substr($var, 0, 6)!=='$_tmp_') throw new PHPTAL_Exception("Invalid variable recycled");
        $this->temp_recycling[] = $var;
    }

    public function getCacheFilesBaseName()
    {
        return $this->_state->getCacheFilesBaseName();
    }

    public function getResult()
    {
        $this->flush();
        if (version_compare(PHP_VERSION, '5.3', '>=') && __NAMESPACE__) {
            return '<?php use '.'PHPTALNAMESPACE as P; ?>'.trim($this->_result);
        } else {
            return trim($this->_result);
        }
    }

    /**
     * set full '<!DOCTYPE...>' string to output later
     *
     * @param string $dt
     *
     * @return void
     */
    public function setDocType($dt)
    {
        $this->_doctype = $dt;
    }

    /**
     * set full '<?xml ?>' string to output later
     *
     * @param string $dt
     *
     * @return void
     */
    public function setXmlDeclaration($dt)
    {
        $this->_xmldeclaration = $dt;
    }

    /**
     * functions later generated and checked for existence will have this prefix added
     * (poor man's namespace)
     *
     * @param string $prefix
     *
     * @return void
     */
    public function setFunctionPrefix($prefix)
    {
        $this->_functionPrefix = $prefix;
    }

    /**
     * @return string
     */
    public function getFunctionPrefix()
    {
        return $this->_functionPrefix;
    }

    /**
     * @see PHPTAL_Php_State::setTalesMode()
     *
     * @param string $mode
     *
     * @return string
     */
    public function setTalesMode($mode)
    {
        return $this->_state->setTalesMode($mode);
    }

    public function splitExpression($src)
    {
        preg_match_all('/(?:[^;]+|;;)+/sm', $src, $array);
        $array = $array[0];
        foreach ($array as &$a) $a = str_replace(';;', ';', $a);
        return $array;
    }

    public function evaluateExpression($src)
    {
        return $this->_state->evaluateExpression($src);
    }

    public function indent()
    {
        $this->_indentation ++;
    }

    public function unindent()
    {
        $this->_indentation --;
    }

    public function flush()
    {
        $this->flushCode();
        $this->flushHtml();
    }

    public function noThrow($bool)
    {
        if ($bool) {
            $this->pushCode('$ctx->noThrow(true)');
        } else {
            $this->pushCode('$ctx->noThrow(false)');
        }
    }

    public function flushCode()
    {
        if (count($this->_codeBuffer) == 0) return;

        // special treatment for one code line
        if (count($this->_codeBuffer) == 1) {
            $codeLine = $this->_codeBuffer[0];
            // avoid adding ; after } and {
            if (!preg_match('/\}\s*$|\{\s*$/', $codeLine))
                $this->_result .= '<?php '.$codeLine."; ?>\n"; // PHP consumes newline
            else
                $this->_result .= '<?php '.$codeLine." ?>\n"; // PHP consumes newline
            $this->_codeBuffer = array();
            return;
        }

        $this->_result .= '<?php '."\n";
        foreach ($this->_codeBuffer as $codeLine) {
            // avoid adding ; after } and {
            if (!preg_match('/[{};]\s*$/', $codeLine)) {
                $codeLine .= ' ;'."\n";
            }
            $this->_result .= $codeLine;
        }
        $this->_result .= "?>\n";// PHP consumes newline
        $this->_codeBuffer = array();
    }

    public function flushHtml()
    {
        if (count($this->_htmlBuffer) == 0) return;

        $this->_result .= implode('', $this->_htmlBuffer);
        $this->_htmlBuffer = array();
    }

    /**
     * Generate code for setting DOCTYPE
     *
     * @param bool $called_from_macro for error checking: unbuffered output doesn't support that
     */
    public function doDoctype($called_from_macro = false)
    {
        if ($this->_doctype) {
            $code = '$ctx->setDocType('.$this->str($this->_doctype).','.($called_from_macro?'true':'false').')';
            $this->pushCode($code);
        }
    }

    /**
     * Generate XML declaration
     *
     * @param bool $called_from_macro for error checking: unbuffered output doesn't support that
     */
    public function doXmlDeclaration($called_from_macro = false)
    {
        if ($this->_xmldeclaration && $this->getOutputMode() !== PHPTAL::HTML5) {
            $code = '$ctx->setXmlDeclaration('.$this->str($this->_xmldeclaration).','.($called_from_macro?'true':'false').')';
            $this->pushCode($code);
        }
    }

    public function functionExists($name)
    {
        return isset($this->known_functions[$this->_functionPrefix . $name]);
    }

    public function doTemplateFile($functionName, PHPTAL_Dom_Element $treeGen)
    {
        $this->doComment("\n*** DO NOT EDIT THIS FILE ***\n\nGenerated by PHPTAL from ".$treeGen->getSourceFile()." (edit that file instead)");
        $this->doFunction($functionName, 'PHPTAL $tpl, PHPTAL_Context $ctx');
        $this->setFunctionPrefix($functionName . "_");
        $this->doSetVar('$_thistpl', '$tpl');
        $this->doInitTranslator();
        $treeGen->generateCode($this);
        $this->doComment("end");
        $this->doEnd('function');
    }

    public function doFunction($name, $params)
    {
        $name = $this->_functionPrefix . $name;
        $this->known_functions[$name] = true;

        $this->pushCodeWriterContext();
        $this->pushCode("function $name($params) {\n");
        $this->indent();
        $this->_segments[] =  'function';
    }

    public function doComment($comment)
    {
        $comment = str_replace('*/', '* /', $comment);
        $this->pushCode("/* $comment */");
    }

    public function doInitTranslator()
    {
        if ($this->_state->isTranslationOn()) {
            $this->doSetVar('$_translator', '$tpl->getTranslator()');
        }
    }

    public function getTranslatorReference()
    {
        if (!$this->_state->isTranslationOn()) {
            throw new PHPTAL_ConfigurationException("i18n used, but Translator has not been set");
        }
        return '$_translator';
    }

    public function doEval($code)
    {
        $this->pushCode($code);
    }

    public function doForeach($out, $source)
    {
        $this->_segments[] =  'foreach';
        $this->pushCode("foreach ($source as $out):");
        $this->indent();
    }

    public function doEnd($expects = null)
    {
        if (!count($this->_segments)) {
            if (!$expects) $expects = 'anything';
            throw new PHPTAL_Exception("Bug: CodeWriter generated end of block without $expects open");
        }

        $segment = array_pop($this->_segments);
        if ($expects !== null && $segment !== $expects) {
            throw new PHPTAL_Exception("Bug: CodeWriter generated end of $expects, but needs to close $segment");
        }

        $this->unindent();
        if ($segment == 'function') {
            $this->pushCode("\n}\n\n");
            $this->flush();
            $functionCode = $this->_result;
            $this->popCodeWriterContext();
            $this->_result = $functionCode . $this->_result;
        } elseif ($segment == 'try')
            $this->pushCode('}');
        elseif ($segment == 'catch')
            $this->pushCode('}');
        else
            $this->pushCode("end$segment");
    }

    public function doTry()
    {
        $this->_segments[] =  'try';
        $this->pushCode('try {');
        $this->indent();
    }

    public function doSetVar($varname, $code)
    {
        $this->pushCode($varname.' = '.$code);
    }

    public function doCatch($catch)
    {
        $this->doEnd('try');
        $this->_segments[] =  'catch';
        $this->pushCode('catch('.$catch.') {');
        $this->indent();
    }

    public function doIf($condition)
    {
        $this->_segments[] =  'if';
        $this->pushCode('if ('.$condition.'): ');
        $this->indent();
    }

    public function doElseIf($condition)
    {
        if (end($this->_segments) !== 'if') {
            throw new PHPTAL_Exception("Bug: CodeWriter generated elseif without if");
        }
        $this->unindent();
        $this->pushCode('elseif ('.$condition.'): ');
        $this->indent();
    }

    public function doElse()
    {
        if (end($this->_segments) !== 'if') {
            throw new PHPTAL_Exception("Bug: CodeWriter generated else without if");
        }
        $this->unindent();
        $this->pushCode('else: ');
        $this->indent();
    }

    public function doEcho($code)
    {
        if ($code === "''") return;
        $this->flush();
        $this->pushCode('echo '.$this->escapeCode($code));
    }

    public function doEchoRaw($code)
    {
        if ($code === "''") return;
        $this->pushCode('echo '.$this->stringifyCode($code));
    }

    public function interpolateHTML($html)
    {
        return $this->_state->interpolateTalesVarsInHtml($html);
    }

    public function interpolateCDATA($str)
    {
        return $this->_state->interpolateTalesVarsInCDATA($str);
    }

    public function pushHTML($html)
    {
        if ($html === "") return;
        $this->flushCode();
        $this->_htmlBuffer[] =  $html;
    }

    public function pushCode($codeLine)
    {
        $this->flushHtml();
        $codeLine = $this->indentSpaces() . $codeLine;
        $this->_codeBuffer[] =  $codeLine;
    }

    /**
     * php string with escaped text
     */
    public function str($string)
    {
        return "'".strtr($string,array("'"=>'\\\'','\\'=>'\\\\'))."'";
    }

    public function escapeCode($code)
    {
        return $this->_state->htmlchars($code);
    }

    public function stringifyCode($code)
    {
        return $this->_state->stringify($code);
    }

    public function getEncoding()
    {
        return $this->_state->getEncoding();
    }

    public function interpolateTalesVarsInString($src)
    {
        return $this->_state->interpolateTalesVarsInString($src);
    }

    public function setDebug($bool)
    {
        return $this->_state->setDebug($bool);
    }

    public function isDebugOn()
    {
        return $this->_state->isDebugOn();
    }

    public function getOutputMode()
    {
        return $this->_state->getOutputMode();
    }

    public function quoteAttributeValue($value)
    {
        // FIXME: interpolation is done _after_ that function, so ${} must be forbidden for now

        if ($this->getEncoding() == 'UTF-8') // HTML 5: 8.1.2.3 Attributes ; http://code.google.com/p/html5lib/issues/detail?id=93
        {
            // regex excludes unicode control characters, all kinds of whitespace and unsafe characters
            // and trailing / to avoid confusion with self-closing syntax
            $unsafe_attr_regex = '/^$|[&=\'"><\s`\pM\pC\pZ\p{Pc}\p{Sk}]|\/$|\${/u';
        } else {
            $unsafe_attr_regex = '/^$|[&=\'"><\s`\0177-\377]|\/$|\${/';
        }

        if ($this->getOutputMode() == PHPTAL::HTML5 && !preg_match($unsafe_attr_regex, $value)) {
            return $value;
        } else {
            return '"'.$value.'"';
        }
    }

    public function pushContext()
    {
        $this->doSetVar('$ctx', '$tpl->pushContext()');
    }

    public function popContext()
    {
        $this->doSetVar('$ctx', '$tpl->popContext()');
    }

    // ~~~~~ Private members ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    private function indentSpaces()
    {
        return str_repeat("\t", $this->_indent);
    }

    private function pushCodeWriterContext()
    {
        $this->_contexts[] =  clone $this;
        $this->_result = "";
        $this->_indent = 0;
        $this->_codeBuffer = array();
        $this->_htmlBuffer = array();
        $this->_segments = array();
    }

    private function popCodeWriterContext()
    {
        $oldContext = array_pop($this->_contexts);
        $this->_result = $oldContext->_result;
        $this->_indent = $oldContext->_indent;
        $this->_codeBuffer = $oldContext->_codeBuffer;
        $this->_htmlBuffer = $oldContext->_htmlBuffer;
        $this->_segments = $oldContext->_segments;
    }

    private $_state;
    private $_result = "";
    private $_indent = 0;
    private $_codeBuffer = array();
    private $_htmlBuffer = array();
    private $_segments = array();
    private $_contexts = array();
    private $_functionPrefix = "";
    private $_doctype = "";
    private $_xmldeclaration = "";
}

