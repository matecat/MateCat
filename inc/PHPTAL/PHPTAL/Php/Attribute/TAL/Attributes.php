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
 * TAL Specifications 1.4
 *
 *       argument             ::= attribute_statement [';' attribute_statement]*
 *       attribute_statement  ::= attribute_name expression
 *       attribute_name       ::= [namespace ':'] Name
 *       namespace            ::= Name
 *
 * examples:
 *
 *      <a href="/sample/link.html"
 *         tal:attributes="href here/sub/absolute_url">
 *      <textarea rows="80" cols="20"
 *         tal:attributes="rows request/rows;cols request/cols">
 *
 * IN PHPTAL: attributes will not work on structured replace.
 *
 * @package PHPTAL
 * @subpackage Php.attribute.tal
 * @author Laurent Bedubourg <lbedubourg@motion-twin.com>
 */
class PHPTAL_Php_Attribute_TAL_Attributes
extends PHPTAL_Php_Attribute
implements PHPTAL_Php_TalesChainReader
{
    /** before creates several variables that need to be freed in after */
    private $vars_to_recycle = array();

    /**
     * value for default keyword
     */
    private $_default_escaped;

    public function before(PHPTAL_Php_CodeWriter $codewriter)
    {
        // split attributes using ; delimiter
        $attrs = $codewriter->splitExpression($this->expression);
        foreach ($attrs as $exp) {
            list($qname, $expression) = $this->parseSetExpression($exp);
            if ($expression) {
                $this->prepareAttribute($codewriter, $qname, $expression);
            }
        }
    }

    private function prepareAttribute(PHPTAL_Php_CodeWriter $codewriter, $qname, $expression)
    {
        $tales_code = $this->extractEchoType($expression);
        $code = $codewriter->evaluateExpression($tales_code);

        // XHTML boolean attribute does not appear when empty or false
        if (PHPTAL_Dom_Defs::getInstance()->isBooleanAttribute($qname)) {

            // I don't want to mix code for boolean with chained executor
            // so compile it again to simple expression
            if (is_array($code)) {
                $code = PHPTAL_Php_TalesInternal::compileToPHPExpression($tales_code);
            }
            return $this->prepareBooleanAttribute($codewriter, $qname, $code);
        }

        // if $code is an array then the attribute value is decided by a
        // tales chained expression
        if (is_array($code)) {
            return $this->prepareChainedAttribute($codewriter, $qname, $code);
        }

        // i18n needs to read replaced value of the attribute, which is not possible if attribute is completely replaced with conditional code
        if ($this->phpelement->hasAttributeNS('http://xml.zope.org/namespaces/i18n', 'attributes')) {
            $this->prepareAttributeUnconditional($codewriter, $qname, $code);
        } else {
            $this->prepareAttributeConditional($codewriter, $qname, $code);
        }
    }

    /**
     * attribute will be output regardless of its evaluated value. NULL behaves just like "".
     */
    private function prepareAttributeUnconditional(PHPTAL_Php_CodeWriter $codewriter, $qname, $code)
    {
        // regular attribute which value is the evaluation of $code
        $attkey = $this->getVarName($qname, $codewriter);
        if ($this->_echoType == PHPTAL_Php_Attribute::ECHO_STRUCTURE) {
            $value = $codewriter->stringifyCode($code);
        } else {
            $value = $codewriter->escapeCode($code);
        }
        $codewriter->doSetVar($attkey, $value);
        $this->phpelement->getOrCreateAttributeNode($qname)->overwriteValueWithVariable($attkey);
    }

    /**
     * If evaluated value of attribute is NULL, it will not be output at all.
     */
    private function prepareAttributeConditional(PHPTAL_Php_CodeWriter $codewriter, $qname, $code)
    {
        // regular attribute which value is the evaluation of $code
        $attkey = $this->getVarName($qname, $codewriter);

        $codewriter->doIf("null !== ($attkey = ($code))");

        if ($this->_echoType !== PHPTAL_Php_Attribute::ECHO_STRUCTURE)
            $codewriter->doSetVar($attkey, $codewriter->str(" $qname=\"").".".$codewriter->escapeCode($attkey).".'\"'");
        else
            $codewriter->doSetVar($attkey, $codewriter->str(" $qname=\"").".".$codewriter->stringifyCode($attkey).".'\"'");

        $codewriter->doElse();
        $codewriter->doSetVar($attkey, "''");
        $codewriter->doEnd('if');

        $this->phpelement->getOrCreateAttributeNode($qname)->overwriteFullWithVariable($attkey);
    }

    private function prepareChainedAttribute(PHPTAL_Php_CodeWriter $codewriter, $qname, $chain)
    {
        $this->_default_escaped = false;
        $this->_attribute = $qname;
        if ($default_attr = $this->phpelement->getAttributeNode($qname)) {
            $this->_default_escaped = $default_attr->getValueEscaped();
        }
        $this->_attkey = $this->getVarName($qname, $codewriter);
        $executor = new PHPTAL_Php_TalesChainExecutor($codewriter, $chain, $this);
        $this->phpelement->getOrCreateAttributeNode($qname)->overwriteFullWithVariable($this->_attkey);
    }

    private function prepareBooleanAttribute(PHPTAL_Php_CodeWriter $codewriter, $qname, $code)
    {
        $attkey = $this->getVarName($qname, $codewriter);

        if ($codewriter->getOutputMode() === PHPTAL::HTML5) {
            $value  = "' $qname'";
        } else {
            $value  = "' $qname=\"$qname\"'";
        }
        $codewriter->doIf($code);
        $codewriter->doSetVar($attkey, $value);
        $codewriter->doElse();
        $codewriter->doSetVar($attkey, '\'\'');
        $codewriter->doEnd('if');
        $this->phpelement->getOrCreateAttributeNode($qname)->overwriteFullWithVariable($attkey);
    }

    private function getVarName($qname, PHPTAL_Php_CodeWriter $codewriter)
    {
        $var = $codewriter->createTempVariable();
        $this->vars_to_recycle[] = $var;
        return $var;
    }


    public function after(PHPTAL_Php_CodeWriter $codewriter)
    {
        foreach ($this->vars_to_recycle as $var) $codewriter->recycleTempVariable($var);
    }

    public function talesChainNothingKeyword(PHPTAL_Php_TalesChainExecutor $executor)
    {
        $codewriter = $executor->getCodeWriter();
        $executor->doElse();
        $codewriter->doSetVar(
            $this->_attkey,
            "''"
        );
        $executor->breakChain();
    }

    public function talesChainDefaultKeyword(PHPTAL_Php_TalesChainExecutor $executor)
    {
        $codewriter = $executor->getCodeWriter();
        $executor->doElse();
        $attr_str = ($this->_default_escaped !== false)
            ? ' '.$this->_attribute.'='.$codewriter->quoteAttributeValue($this->_default_escaped)  // default value
            : '';                                 // do not print attribute
        $codewriter->doSetVar($this->_attkey, $codewriter->str($attr_str));
        $executor->breakChain();
    }

    public function talesChainPart(PHPTAL_Php_TalesChainExecutor $executor, $exp, $islast)
    {
        $codewriter = $executor->getCodeWriter();

        if (!$islast) {
            $condition = "!phptal_isempty($this->_attkey = ($exp))";
        } else {
            $condition = "null !== ($this->_attkey = ($exp))";
        }
        $executor->doIf($condition);

        if ($this->_echoType == PHPTAL_Php_Attribute::ECHO_STRUCTURE)
            $value = $codewriter->stringifyCode($this->_attkey);
        else
            $value = $codewriter->escapeCode($this->_attkey);

        $codewriter->doSetVar($this->_attkey, $codewriter->str(" {$this->_attribute}=\"").".$value.'\"'");
    }
}

