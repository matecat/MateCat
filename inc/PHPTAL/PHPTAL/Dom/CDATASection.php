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
 * Outputs <![CDATA[ ]]> blocks, sometimes converts them to text
 * @todo this might be moved to CDATA processing in Element
 *
 * @package PHPTAL
 * @subpackage Dom
 */
class PHPTAL_Dom_CDATASection extends PHPTAL_Dom_Node
{
    public function generateCode(PHPTAL_Php_CodeWriter $codewriter)
    {
        $mode = $codewriter->getOutputMode();
        $value = $this->getValueEscaped();
        $inCDATAelement = PHPTAL_Dom_Defs::getInstance()->isCDATAElementInHTML($this->parentNode->getNamespaceURI(), $this->parentNode->getLocalName());

        // in HTML5 must limit it to <script> and <style>
        if ($mode === PHPTAL::HTML5 && $inCDATAelement) {
            $codewriter->pushHTML($codewriter->interpolateCDATA(str_replace('</', '<\/', $value)));
        } elseif (($mode === PHPTAL::XHTML && $inCDATAelement)  // safe for text/html
             || ($mode === PHPTAL::XML && preg_match('/[<>&]/', $value))  // non-useless in XML
             || ($mode !== PHPTAL::HTML5 && preg_match('/<\?|\${structure/', $value)))  // hacks with structure (in X[HT]ML) may need it
        {
            // in text/html "</" is dangerous and the only sensible way to escape is ECMAScript string escapes.
            if ($mode === PHPTAL::XHTML) $value = str_replace('</', '<\/', $value);

            $codewriter->pushHTML($codewriter->interpolateCDATA('<![CDATA['.$value.']]>'));
        } else {
            $codewriter->pushHTML($codewriter->interpolateHTML(htmlspecialchars($value)));
        }
    }
}
