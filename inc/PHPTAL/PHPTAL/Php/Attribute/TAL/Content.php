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


/** TAL Specifications 1.4
 *
 *     argument ::= (['text'] | 'structure') expression
 *
 * Example:
 *
 *     <p tal:content="user/name">Fred Farkas</p>
 *
 *
 *
 *
 * @package PHPTAL
 * @subpackage Php.attribute.tal
 * @author Laurent Bedubourg <lbedubourg@motion-twin.com>
 */
class PHPTAL_Php_Attribute_TAL_Content
extends PHPTAL_Php_Attribute
implements PHPTAL_Php_TalesChainReader
{
    public function before(PHPTAL_Php_CodeWriter $codewriter)
    {
        $expression = $this->extractEchoType($this->expression);

        $code = $codewriter->evaluateExpression($expression);

        if (is_array($code)) {
            return $this->generateChainedContent($codewriter, $code);
        }

        if ($code == PHPTAL_Php_TalesInternal::NOTHING_KEYWORD) {
            return;
        }

        if ($code == PHPTAL_Php_TalesInternal::DEFAULT_KEYWORD) {
            return $this->generateDefault($codewriter);
        }

        $this->doEchoAttribute($codewriter, $code);
    }

    public function after(PHPTAL_Php_CodeWriter $codewriter)
    {
    }

    private function generateDefault(PHPTAL_Php_CodeWriter $codewriter)
    {
        $this->phpelement->generateContent($codewriter, true);
    }

    protected function generateChainedContent(PHPTAL_Php_CodeWriter $codewriter, $code)
    {
        $executor = new PHPTAL_Php_TalesChainExecutor($codewriter, $code, $this);
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

    public function talesChainNothingKeyword(PHPTAL_Php_TalesChainExecutor $executor)
    {
        $executor->breakChain();
    }

    public function talesChainDefaultKeyword(PHPTAL_Php_TalesChainExecutor $executor)
    {
        $executor->doElse();
        $this->generateDefault($executor->getCodeWriter());
        $executor->breakChain();
    }
}
