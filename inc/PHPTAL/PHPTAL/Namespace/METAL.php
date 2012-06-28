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
class PHPTAL_Namespace_METAL extends PHPTAL_Namespace_Builtin
{
    public function __construct()
    {
        parent::__construct('metal', 'http://xml.zope.org/namespaces/metal');
        $this->addAttribute(new PHPTAL_NamespaceAttributeSurround('define-macro', 1));
        $this->addAttribute(new PHPTAL_NamespaceAttributeReplace('use-macro', 9));
        $this->addAttribute(new PHPTAL_NamespaceAttributeSurround('define-slot', 9));
        $this->addAttribute(new PHPTAL_NamespaceAttributeSurround('fill-slot', 9));
    }
}
