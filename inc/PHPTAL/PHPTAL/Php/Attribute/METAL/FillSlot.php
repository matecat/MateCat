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
 *  METAL Specification 1.0
 *
 *      argument ::= Name
 *
 * Example:
 *
 *       <table metal:use-macro="here/doc1/macros/sidebar">
 *        <tr><th>Links</th></tr>
 *        <tr><td metal:fill-slot="links">
 *          <a href="http://www.goodplace.com">Good Place</a><br>
 *          <a href="http://www.badplace.com">Bad Place</a><br>
 *          <a href="http://www.otherplace.com">Other Place</a>
 *        </td></tr>
 *      </table>
 *
 * PHPTAL:
 *
 * 1. evaluate slots
 *
 * <?php ob_start(); ? >
 * <td>
 *   <a href="http://www.goodplace.com">Good Place</a><br>
 *   <a href="http://www.badplace.com">Bad Place</a><br>
 *   <a href="http://www.otherplace.com">Other Place</a>
 * </td>
 * <?php $tpl->slots->links = ob_get_contents(); ob_end_clean(); ? >
 *
 * 2. call the macro (here not supported)
 *
 * <?php echo phptal_macro($tpl, 'master_page.html/macros/sidebar'); ? >
 *
 *
 * @package PHPTAL
 * @subpackage Php.attribute.metal
 * @author Laurent Bedubourg <lbedubourg@motion-twin.com>
 */
class PHPTAL_Php_Attribute_METAL_FillSlot extends PHPTAL_Php_Attribute
{
    private static $uid = 0;
    private $function_name;

    public function before(PHPTAL_Php_CodeWriter $codewriter)
    {
        if ($this->shouldUseCallback()) {
            $function_base_name = 'slot_'.preg_replace('/[^a-z0-9]/', '_', $this->expression).'_'.(self::$uid++);
            $codewriter->doFunction($function_base_name, 'PHPTAL $_thistpl, PHPTAL $tpl');
            $this->function_name = $codewriter->getFunctionPrefix().$function_base_name;

            $codewriter->doSetVar('$ctx', '$tpl->getContext()');
            $codewriter->doInitTranslator();
        } else {
            $codewriter->pushCode('ob_start()');
            $this->function_name = null;
        }
    }

    public function after(PHPTAL_Php_CodeWriter $codewriter)
    {
        if ($this->function_name !== null) {
            $codewriter->doEnd();
            $codewriter->pushCode('$ctx->fillSlotCallback('.$codewriter->str($this->expression).', '.$codewriter->str($this->function_name).', $_thistpl, clone $tpl)');
        } else {
            $codewriter->pushCode('$ctx->fillSlot('.$codewriter->str($this->expression).', ob_get_clean())');
        }
    }

    // rough guess
    const CALLBACK_THRESHOLD = 10000;

    /**
     * inspects contents of the element to decide whether callback makes sense
     */
    private function shouldUseCallback()
    {
        // since callback is slightly slower than buffering,
        // use callback only for content that is large to offset speed loss by memory savings
        return $this->estimateNumberOfBytesOutput($this->phpelement, false) > self::CALLBACK_THRESHOLD;
    }

    /**
     * @param bool $is_nested_in_repeat true if any parent element has tal:repeat
     *
     * @return rough guess
     */
    private function estimateNumberOfBytesOutput(PHPTAL_Dom_Element $element, $is_nested_in_repeat)
    {
        // macros don't output anything on their own
        if ($element->hasAttributeNS('http://xml.zope.org/namespaces/metal', 'define-macro')) {
            return 0;
        }

        $estimated_bytes = 2*(3+strlen($element->getQualifiedName()));

        foreach ($element->getAttributeNodes() as $attr) {
            $estimated_bytes += 4+strlen($attr->getQualifiedName());
            if ($attr->getReplacedState() === PHPTAL_Dom_Attr::NOT_REPLACED) {
                $estimated_bytes += strlen($attr->getValueEscaped()); // this is shoddy for replaced attributes
            }
        }

        $has_repeat_attr = $element->hasAttributeNS('http://xml.zope.org/namespaces/tal', 'repeat');

        if ($element->hasAttributeNS('http://xml.zope.org/namespaces/tal', 'content') ||
            $element->hasAttributeNS('http://xml.zope.org/namespaces/tal', 'replace')) {
            // assume that output in loops is shorter (e.g. table rows) than outside (main content)
            $estimated_bytes += ($has_repeat_attr || $is_nested_in_repeat) ? 500 : 2000;
        } else {
            foreach ($element->childNodes as $node) {
                if ($node instanceof PHPTAL_Dom_Element) {
                    $estimated_bytes += $this->estimateNumberOfBytesOutput($node, $has_repeat_attr || $is_nested_in_repeat);
                } else {
                    $estimated_bytes += strlen($node->getValueEscaped());
                }
            }
        }

        if ($element->hasAttributeNS('http://xml.zope.org/namespaces/metal', 'use-macro')) {
            $estimated_bytes += ($has_repeat_attr || $is_nested_in_repeat) ? 500 : 2000;
        }

        if ($element->hasAttributeNS('http://xml.zope.org/namespaces/tal', 'condition')) {
            $estimated_bytes /= 2; // naively assuming 50% chance, that works well with if/else pattern
        }

        if ($element->hasAttributeNS('http://xml.zope.org/namespaces/tal', 'repeat')) {
            // assume people don't write big nested loops
            $estimated_bytes *= $is_nested_in_repeat ? 5 : 10;
        }

        return $estimated_bytes;
    }
}
