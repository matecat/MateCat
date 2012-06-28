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

/** i18n:name
 *
 * Name the content of the current element for use in interpolation within
 * translated content. This allows a replaceable component in content to be
 * re-ordered by translation. For example:
 *
 * <span i18n:translate=''>
 *   <span tal:replace='here/name' i18n:name='name' /> was born in
 *   <span tal:replace='here/country_of_birth' i18n:name='country' />.
 * </span>
 *
 * would cause this text to be passed to the translation service:
 *
 *     "${name} was born in ${country}."
 *
 *
 * @package PHPTAL
 * @subpackage Php.attribute.i18n
 */
class PHPTAL_Php_Attribute_I18N_Name extends PHPTAL_Php_Attribute
{
    public function before(PHPTAL_Php_CodeWriter $codewriter)
    {
        $codewriter->pushCode('ob_start()');
    }

    public function after(PHPTAL_Php_CodeWriter $codewriter)
    {
        $codewriter->pushCode($codewriter->getTranslatorReference().'->setVar('.$codewriter->str($this->expression).', ob_get_clean())');
    }
}

