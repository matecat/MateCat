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

/**
 * Collapses conscutive whitespace, trims attributes, merges adjacent text nodes
 */
class PHPTAL_PreFilter_Normalize extends PHPTAL_PreFilter
{
    function filter($src)
    {
        return str_replace("\r\n", "\n", $src);
    }

    function filterDOM(PHPTAL_Dom_Element $root)
    {
        // let xml:space=preserve preserve attributes as well
        if ($root->getAttributeNS("http://www.w3.org/XML/1998/namespace", 'space') == 'preserve') {
            $this->findElementToFilter($root);
            return;
        }

        $this->normalizeAttributes($root);

        // <pre> may have attributes normalized
        if ($this->isSpaceSensitiveInXHTML($root)) {
            $this->findElementToFilter($root);
            return;
        }

        $lastTextNode = null;
        foreach ($root->childNodes as $node) {

            // CDATA is not normalized by design
            if ($node instanceof PHPTAL_Dom_Text) {
                $norm = $this->normalizeSpace($node->getValueEscaped(), $node->getEncoding());
                $node->setValueEscaped($norm);

                if ('' === $norm) {
                    $root->removeChild($node);
                } else if ($lastTextNode) {
                    // "foo " . " bar" gives 2 spaces.
                    $norm = $lastTextNode->getValueEscaped().ltrim($norm,' ');

                    $lastTextNode->setValueEscaped($norm); // assumes all nodes use same encoding (they do)
                    $root->removeChild($node);
                } else {
                    $lastTextNode = $node;
                }
            } else {
                $lastTextNode = null;
                if ($node instanceof PHPTAL_Dom_Element) {
                    $this->filterDOM($node);
                }
            }
        }
    }

    protected function isSpaceSensitiveInXHTML(PHPTAL_Dom_Element $element)
    {
        $ln = $element->getLocalName();
        return ($ln === 'script' || $ln === 'pre' || $ln === 'textarea')
            && ($element->getNamespaceURI() === 'http://www.w3.org/1999/xhtml' || $element->getNamespaceURI() === '');
    }

    protected function findElementToFilter(PHPTAL_Dom_Element $root)
    {
        foreach ($root->childNodes as $node) {
            if (!$node instanceof PHPTAL_Dom_Element) continue;

            if ($node->getAttributeNS("http://www.w3.org/XML/1998/namespace", 'space') == 'default') {
                $this->filterDOM($node);
            }
        }
    }

    /**
     * does not trim
     */
    protected function normalizeSpace($text, $encoding)
    {
        $utf_regex_mod = ($encoding=='UTF-8'?'u':'');

        return preg_replace('/[ \t\r\n]+/'.$utf_regex_mod, ' ', $text); // \s removes nbsp
    }

    protected function normalizeAttributes(PHPTAL_Dom_Element $element)
    {
        foreach ($element->getAttributeNodes() as $attrnode) {

            // skip replaced attributes (because getValueEscaped on them is meaningless)
            if ($attrnode->getReplacedState() !== PHPTAL_Dom_Attr::NOT_REPLACED) continue;

            $val = $this->normalizeSpace($attrnode->getValueEscaped(), $attrnode->getEncoding());
            $attrnode->setValueEscaped(trim($val, ' '));
        }
    }
}
