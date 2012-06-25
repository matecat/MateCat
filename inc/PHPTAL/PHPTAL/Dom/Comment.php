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
 * @package PHPTAL
 * @subpackage Dom
 */
class PHPTAL_Dom_Comment extends PHPTAL_Dom_Node
{
    public function generateCode(PHPTAL_Php_CodeWriter $codewriter)
    {
        if (!preg_match('/^\s*!/', $this->getValueEscaped())) {
            $codewriter->pushHTML('<!--'.$this->getValueEscaped().'-->');
        }
    }
}
