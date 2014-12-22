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
 *      argument ::= (['text'] | 'structure') expression
 *
 *  Default behaviour : text
 *
 *      <span tal:replace="template/title">Title</span>
 *      <span tal:replace="text template/title">Title</span>
 *      <span tal:replace="structure table" />
 *      <span tal:replace="nothing">This element is a comment.</span>
 *
 *
 *
 * @package PHPTAL
 * @subpackage Php.attribute.tal
 * @author Laurent Bedubourg <lbedubourg@motion-twin.com>
 */
class PHPTAL_Php_Attribute_TAL_Replace
extends PHPTAL_Php_Attribute
implements PHPTAL_Php_TalesChainReader
{
    public function before(PHPTAL_Php_CodeWriter $codewriter)
    {
        // tal:replace="" => do nothing and ignore node
        if (trim($this->expression) == "") {
            return;
        }

        $expression = $this->extractEchoType($this->expression);
        $code = $codewriter->evaluateExpression($expression);

        // chained expression
        if (is_array($code)) {
            return $this->replaceByChainedExpression($codewriter, $code);
        }

        // nothing do nothing
        if ($code == PHPTAL_Php_TalesInternal::NOTHING_KEYWORD) {
            return;
        }

        // default generate default tag content
        if ($code == PHPTAL_Php_TalesInternal::DEFAULT_KEYWORD) {
            return $this->generateDefault($codewriter);
        }

        // replace tag with result of expression
        $this->doEchoAttribute($codewriter, $code);
    }

    public function after(PHPTAL_Php_CodeWriter $codewriter)
    {
    }

    /**
     * support expressions like "foo | bar"
     */
    private function replaceByChainedExpression(PHPTAL_Php_CodeWriter $codewriter, $expArray)
    {
        $executor = new PHPTAL_Php_TalesChainExecutor(
            $codewriter, $expArray, $this
        );
    }

    public function talesChainNothingKeyword(PHPTAL_Php_TalesChainExecutor $executor)
    {
        $executor->continueChain();
    }

    public function talesChainDefaultKeyword(PHPTAL_Php_TalesChainExecutor $executor)
    {
        $executor->doElse();
        $this->generateDefault($executor->getCodeWriter());
        $executor->breakChain();
    }

    public function talesChainPart(PHPTAL_Php_TalesChainExecutor $executor, $exp, $islast)
    {
        if (!$islast) {
            $var = $executor->getCodeWriter()->createTempVariable();
            $executor->doIf('!phptal_isempty('.$var.' = '.$exp.')');
            $this->doEchoAttribute($executor->getCodeWriter(), $var);
            $executor->getCodeWriter()->recycleTempVariable($var);
        } else {
            $executor->doElse();
            $this->doEchoAttribute($executor->getCodeWriter(), $exp);
        }
    }

    /**
     * don't replace - re-generate default content
     */
    private function generateDefault(PHPTAL_Php_CodeWriter $codewriter)
    {
        $this->phpelement->generateSurroundHead($codewriter);
        $this->phpelement->generateHead($codewriter);
        $this->phpelement->generateContent($codewriter);
        $this->phpelement->generateFoot($codewriter);
        $this->phpelement->generateSurroundFoot($codewriter);
    }
}

