<?php
/**
 * PHPTAL templating engine
 *
 * PHP Version 5
 *
 * @category HTML
 * @package  PHPTAL
 * @author   Kornel Lesiński <kornel@aardvarkmedia.co.uk>
 * @license  http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @version  SVN: $Id: $
 * @link     http://phptal.org/
 */

class PHPTAL_PreFilter_StripComments extends PHPTAL_PreFilter
{
    function filterDOM(PHPTAL_Dom_Element $element)
    {
        $defs = PHPTAL_Dom_Defs::getInstance();

        foreach ($element->childNodes as $node) {
            if ($node instanceof PHPTAL_Dom_Comment) {
                if ($defs->isCDATAElementInHTML($element->getNamespaceURI(), $element->getLocalName())) {
                    $textNode = new PHPTAL_Dom_CDATASection($node->getValueEscaped(), $node->getEncoding());
                    $node->parentNode->replaceChild($textNode, $node);
                } else {
                    $node->parentNode->removeChild($node);
                }
            } else if ($node instanceof PHPTAL_Dom_Element) {
                $this->filterDOM($node);
            }
        }
    }
}
