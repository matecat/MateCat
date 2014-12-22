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
 * METAL Specification 1.0
 *
 *      argument ::= Name
 *
 * Example:
 *
 *      <p metal:define-macro="copyright">
 *      Copyright 2001, <em>Foobar</em> Inc.
 *      </p>
 *
 * PHPTAL:
 *
 *      <?php function XXX_macro_copyright($tpl) { ? >
 *        <p>
 *        Copyright 2001, <em>Foobar</em> Inc.
 *        </p>
 *      <?php } ? >
 *
 * @package PHPTAL
 * @subpackage Php.attribute.metal
 * @author Laurent Bedubourg <lbedubourg@motion-twin.com>
 */
class PHPTAL_Php_Attribute_METAL_DefineMacro extends PHPTAL_Php_Attribute
{
    public function before(PHPTAL_Php_CodeWriter $codewriter)
    {
        $macroname = strtr(trim($this->expression), '-', '_');
        if (!preg_match('/^[a-z0-9_]+$/i', $macroname)) {
            throw new PHPTAL_ParserException('Bad macro name "'.$macroname.'"',
                $this->phpelement->getSourceFile(), $this->phpelement->getSourceLine());
        }

        if ($codewriter->functionExists($macroname)) {
            throw new PHPTAL_TemplateException("Macro $macroname is defined twice",
                $this->phpelement->getSourceFile(), $this->phpelement->getSourceLine());
        }

        $codewriter->doFunction($macroname, 'PHPTAL $_thistpl, PHPTAL $tpl');
        $codewriter->doSetVar('$tpl', 'clone $tpl');
        $codewriter->doSetVar('$ctx', '$tpl->getContext()');
        $codewriter->doInitTranslator();
        $codewriter->doXmlDeclaration(true);
        $codewriter->doDoctype(true);
    }

    public function after(PHPTAL_Php_CodeWriter $codewriter)
    {
        $codewriter->doEnd('function');
    }
}

