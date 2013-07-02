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
 * TAL spec 1.4 for tal:define content
 *
 * argument       ::= define_scope [';' define_scope]*
 * define_scope   ::= (['local'] | 'global') define_var
 * define_var     ::= variable_name expression
 * variable_name  ::= Name
 *
 * Note: If you want to include a semi-colon (;) in an expression, it must be escaped by doubling it (;;).*
 *
 * examples:
 *
 *   tal:define="mytitle template/title; tlen python:len(mytitle)"
 *   tal:define="global company_name string:Digital Creations, Inc."
 *
 *
 *
 * @package PHPTAL
 * @subpackage Php.attribute.tal
 * @author Laurent Bedubourg <lbedubourg@motion-twin.com>
 */
class PHPTAL_Php_Attribute_TAL_Define
extends PHPTAL_Php_Attribute
implements PHPTAL_Php_TalesChainReader
{
    private $tmp_content_var;
    private $_buffered = false;
    private $_defineScope = null;
    private $_defineVar = null;
    private $_pushedContext = false;
    /**
     * Prevents generation of invalid PHP code when given invalid TALES
     */
    private $_chainPartGenerated=false;

    public function before(PHPTAL_Php_CodeWriter $codewriter)
    {
        $expressions = $codewriter->splitExpression($this->expression);
        $definesAnyNonGlobalVars = false;

        foreach ($expressions as $exp) {
            list($defineScope, $defineVar, $expression) = $this->parseExpression($exp);
            if (!$defineVar) {
                continue;
            }

            $this->_defineScope = $defineScope;

            // <span tal:define="global foo" /> should be invisible, but <img tal:define="bar baz" /> not
            if ($defineScope != 'global') $definesAnyNonGlobalVars = true;

            if ($this->_defineScope != 'global' && !$this->_pushedContext) {
                $codewriter->pushContext();
                $this->_pushedContext = true;
            }

            $this->_defineVar = $defineVar;
            if ($expression === null) {
                // no expression give, use content of tag as value for newly defined var.
                $this->bufferizeContent($codewriter);
                continue;
            }

            $code = $codewriter->evaluateExpression($expression);
            if (is_array($code)) {
                $this->chainedDefine($codewriter, $code);
            } elseif ( $code == PHPTAL_Php_TalesInternal::NOTHING_KEYWORD) {
                $this->doDefineVarWith($codewriter, 'null');
            } else {
                $this->doDefineVarWith($codewriter, $code);
            }
        }

        // if the content of the tag was buffered or the tag has nothing to tell, we hide it.
        if ($this->_buffered || (!$definesAnyNonGlobalVars && !$this->phpelement->hasRealContent() && !$this->phpelement->hasRealAttributes())) {
            $this->phpelement->hidden = true;
        }
    }

    public function after(PHPTAL_Php_CodeWriter $codewriter)
    {
        if ($this->tmp_content_var) $codewriter->recycleTempVariable($this->tmp_content_var);
        if ($this->_pushedContext) {
            $codewriter->popContext();
        }
    }

    private function chainedDefine(PHPTAL_Php_CodeWriter $codewriter, $parts)
    {
        $executor = new PHPTAL_Php_TalesChainExecutor(
            $codewriter, $parts, $this
        );
    }

    public function talesChainNothingKeyword(PHPTAL_Php_TalesChainExecutor $executor)
    {
        if (!$this->_chainPartGenerated) throw new PHPTAL_TemplateException("Invalid expression in tal:define", $this->phpelement->getSourceFile(), $this->phpelement->getSourceLine());

        $executor->doElse();
        $this->doDefineVarWith($executor->getCodeWriter(), 'null');
        $executor->breakChain();
    }

    public function talesChainDefaultKeyword(PHPTAL_Php_TalesChainExecutor $executor)
    {
        if (!$this->_chainPartGenerated) throw new PHPTAL_TemplateException("Invalid expression in tal:define", $this->phpelement->getSourceFile(), $this->phpelement->getSourceLine());

        $executor->doElse();
        $this->bufferizeContent($executor->getCodeWriter());
        $executor->breakChain();
    }

    public function talesChainPart(PHPTAL_Php_TalesChainExecutor $executor, $exp, $islast)
    {
        $this->_chainPartGenerated=true;

        if ($this->_defineScope == 'global') {
            $var = '$tpl->getGlobalContext()->'.$this->_defineVar;
        } else {
            $var = '$ctx->'.$this->_defineVar;
        }

        $cw = $executor->getCodeWriter();

        if (!$islast) {
            // must use temp variable, because expression could refer to itself
            $tmp = $cw->createTempVariable();
            $executor->doIf('('.$tmp.' = '.$exp.') !== null');
            $cw->doSetVar($var, $tmp);
            $cw->recycleTempVariable($tmp);
        } else {
            $executor->doIf('('.$var.' = '.$exp.') !== null');
        }
    }

    /**
     * Parse the define expression, already splitted in sub parts by ';'.
     */
    public function parseExpression($exp)
    {
        $defineScope = false; // (local | global)
        $defineVar   = false; // var to define

        // extract defineScope from expression
        $exp = trim($exp);
        if (preg_match('/^(local|global)\s+(.*?)$/ism', $exp, $m)) {
            list(, $defineScope, $exp) = $m;
            $exp = trim($exp);
        }

        // extract varname and expression from remaining of expression
        list($defineVar, $exp) = $this->parseSetExpression($exp);
        if ($exp !== null) $exp = trim($exp);
        return array($defineScope, $defineVar, $exp);
    }

    private function bufferizeContent(PHPTAL_Php_CodeWriter $codewriter)
    {
        if (!$this->_buffered) {
            $this->tmp_content_var = $codewriter->createTempVariable();
            $codewriter->pushCode( 'ob_start()' );
            $this->phpelement->generateContent($codewriter);
            $codewriter->doSetVar($this->tmp_content_var, 'ob_get_clean()');
            $this->_buffered = true;
        }
        $this->doDefineVarWith($codewriter, $this->tmp_content_var);
    }

    private function doDefineVarWith(PHPTAL_Php_CodeWriter $codewriter, $code)
    {
        if ($this->_defineScope == 'global') {
            $codewriter->doSetVar('$tpl->getGlobalContext()->'.$this->_defineVar, $code);
        } else {
            $codewriter->doSetVar('$ctx->'.$this->_defineVar, $code);
        }
    }
}

