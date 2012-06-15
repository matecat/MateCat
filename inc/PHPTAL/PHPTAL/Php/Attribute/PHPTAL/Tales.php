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
 * @subpackage Php.attribute.phptal
 * @author Laurent Bedubourg <lbedubourg@motion-twin.com>
 */
class PHPTAL_Php_Attribute_PHPTAL_TALES extends PHPTAL_Php_Attribute
{
    public function before(PHPTAL_Php_CodeWriter $codewriter)
    {
        $mode = trim($this->expression);
        $mode = strtolower($mode);

        if ($mode == '' || $mode == 'default')
            $mode = 'tales';

        if ($mode != 'php' && $mode != 'tales') {
            throw new PHPTAL_TemplateException("Unsupported TALES mode '$mode'",
                $this->phpelement->getSourceFile(), $this->phpelement->getSourceLine());
        }

        $this->_oldMode = $codewriter->setTalesMode($mode);
    }

    public function after(PHPTAL_Php_CodeWriter $codewriter)
    {
        $codewriter->setTalesMode($this->_oldMode);
    }

    private $_oldMode;
}

