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
 * Document doctype representation.
 *
 * @package PHPTAL
 * @subpackage Dom
 */
class PHPTAL_Dom_DocumentType extends PHPTAL_Dom_Node
{
    public function generateCode(PHPTAL_Php_CodeWriter $codewriter)
    {
        if ($codewriter->getOutputMode() === PHPTAL::HTML5) {
            $codewriter->setDocType('<!DOCTYPE html>');
        } else {
            $codewriter->setDocType($this->getValueEscaped());
        }
        $codewriter->doDoctype();
    }
}
