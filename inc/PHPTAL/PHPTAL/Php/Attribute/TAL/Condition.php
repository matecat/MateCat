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
 *      argument ::= expression
 *
 * Example:
 *
 *      <p tal:condition="here/copyright"
 *         tal:content="here/copyright">(c) 2000</p>
 *
 *
 *
 *
 * @package PHPTAL
 * @subpackage Php.attribute.tal
 * @author Laurent Bedubourg <lbedubourg@motion-twin.com>
 */
class PHPTAL_Php_Attribute_TAL_Condition
extends PHPTAL_Php_Attribute
implements PHPTAL_Php_TalesChainReader
{
    private $expressions = array();

    public function before(PHPTAL_Php_CodeWriter $codewriter)
    {
        $code = $codewriter->evaluateExpression($this->expression);

        // If it's a chained expression build a new code path
        if (is_array($code)) {
            $this->expressions = array();
            $executor = new PHPTAL_Php_TalesChainExecutor($codewriter, $code, $this);
            return;
        }

        // Force a falsy condition if the nothing keyword is active
        if ($code == PHPTAL_Php_TalesInternal::NOTHING_KEYWORD) {
            $code = 'false';
        }

        $codewriter->doIf('phptal_true(' . $code . ')');
    }

    public function after(PHPTAL_Php_CodeWriter $codewriter)
    {
        $codewriter->doEnd('if');
    }


    public function talesChainPart(PHPTAL_Php_TalesChainExecutor $executor, $exp, $islast)
    {
        // check if the expression is empty
        if ($exp !== 'false') {
            $this->expressions[] = '!phptal_isempty(' . $exp . ')';
        }

        if ($islast) {
            // for the last one in the chain build a ORed condition
            $executor->getCodeWriter()->doIf( implode(' || ', $this->expressions ) );
            // The executor will always end an if so we output a dummy if
            $executor->doIf('false');
        }
    }

    public function talesChainNothingKeyword(PHPTAL_Php_TalesChainExecutor $executor)
    {
        // end the chain
        $this->talesChainPart($executor, 'false', true);
        $executor->breakChain();
    }

    public function talesChainDefaultKeyword(PHPTAL_Php_TalesChainExecutor $executor)
    {
        throw new PHPTAL_ParserException('\'default\' keyword not allowed on conditional expressions',
                    $this->phpelement->getSourceFile(), $this->phpelement->getSourceLine());
    }

}

