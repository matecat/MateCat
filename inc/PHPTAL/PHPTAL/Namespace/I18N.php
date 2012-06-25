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
 * @subpackage Namespace
 */
class PHPTAL_Namespace_I18N extends PHPTAL_Namespace_Builtin
{
    public function __construct()
    {
        parent::__construct('i18n', 'http://xml.zope.org/namespaces/i18n');
        $this->addAttribute(new PHPTAL_NamespaceAttributeContent('translate', 5));
        $this->addAttribute(new PHPTAL_NamespaceAttributeSurround('name', 5));
        $this->addAttribute(new PHPTAL_NamespaceAttributeSurround('attributes', 10));
        $this->addAttribute(new PHPTAL_NamespaceAttributeSurround('domain', 3));
    }
}

