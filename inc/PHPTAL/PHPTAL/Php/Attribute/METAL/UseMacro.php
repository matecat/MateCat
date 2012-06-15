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
 * METAL Specification 1.0
 *
 *      argument ::= expression
 *
 * Example:
 *
 *      <hr />
 *      <p metal:use-macro="here/master_page/macros/copyright">
 *      <hr />
 *
 * PHPTAL: (here not supported)
 *
 *      <?php echo phptal_macro( $tpl, 'master_page.html/macros/copyright'); ? >
 *
 *
 *
 * @package PHPTAL
 * @subpackage Php.attribute.metal
 * @author Laurent Bedubourg <lbedubourg@motion-twin.com>
 */
class PHPTAL_Php_Attribute_METAL_UseMacro extends PHPTAL_Php_Attribute
{
    static $ALLOWED_ATTRIBUTES = array(
        'fill-slot'=>'http://xml.zope.org/namespaces/metal',
        'define-macro'=>'http://xml.zope.org/namespaces/metal',
        'define'=>'http://xml.zope.org/namespaces/tal',
    );

    public function before(PHPTAL_Php_CodeWriter $codewriter)
    {
        $this->pushSlots($codewriter);

        foreach ($this->phpelement->childNodes as $child) {
            $this->generateFillSlots($codewriter, $child);
        }

        $macroname = strtr($this->expression, '-', '_');

	// throw error if attempting to define and use macro at same time
	// [should perhaps be a TemplateException? but I don't know how to set that up...]
	if ($defineAttr = $this->phpelement->getAttributeNodeNS(
		'http://xml.zope.org/namespaces/metal', 'define-macro')) {
		if ($defineAttr->getValue() == $macroname) 
            		throw new PHPTAL_TemplateException("Cannot simultaneously define and use macro '$macroname'",
                		$this->phpelement->getSourceFile(), $this->phpelement->getSourceLine());			
	}

        // local macro (no filename specified) and non dynamic macro name
        // can be called directly if it's a known function (just generated or seen in previous compilation)
        if (preg_match('/^[a-z0-9_]+$/i', $macroname) && $codewriter->functionExists($macroname)) {
            $code = $codewriter->getFunctionPrefix() . $macroname . '($_thistpl, $tpl)';
            $codewriter->pushCode($code);
        }
        // external macro or ${macroname}, use PHPTAL at runtime to resolve it
        else {
            $code = $codewriter->interpolateTalesVarsInString($this->expression);
            $codewriter->pushCode('$tpl->_executeMacroOfTemplate('.$code.', $_thistpl)');
        }

        $this->popSlots($codewriter);
    }

    public function after(PHPTAL_Php_CodeWriter $codewriter)
    {
    }

    /**
     * reset template slots on each macro call ?
     *
     * NOTE: defining a macro and using another macro on the same tag
     * means inheriting from the used macro, thus slots are shared, it
     * is a little tricky to understand but very natural to use.
     *
     * For example, we may have a main design.html containing our main
     * website presentation with some slots (menu, content, etc...) then
     * we may define a member.html macro which use the design.html macro
     * for the general layout, fill the menu slot and let caller templates
     * fill the parent content slot without interfering.
     */
    private function pushSlots(PHPTAL_Php_CodeWriter $codewriter)
    {
        if (!$this->phpelement->hasAttributeNS('http://xml.zope.org/namespaces/metal', 'define-macro')) {
            $codewriter->pushCode('$ctx->pushSlots()');
        }
    }

    /**
     * generate code that pops macro slots
     * (restore slots if not inherited macro)
     */
    private function popSlots(PHPTAL_Php_CodeWriter $codewriter)
    {
        if (!$this->phpelement->hasAttributeNS('http://xml.zope.org/namespaces/metal', 'define-macro')) {
            $codewriter->pushCode('$ctx->popSlots()');
        }
    }

    /**
     * recursively generates code for slots
     */
    private function generateFillSlots(PHPTAL_Php_CodeWriter $codewriter, PHPTAL_Dom_Node $phpelement)
    {
        if (false == ($phpelement instanceof PHPTAL_Dom_Element)) {
            return;
        }

        // if the tag contains one of the allowed attribute, we generate it
        foreach (self::$ALLOWED_ATTRIBUTES as $qname => $uri) {
            if ($phpelement->hasAttributeNS($uri, $qname)) {
                $phpelement->generateCode($codewriter);
                return;
            }
        }

        foreach ($phpelement->childNodes as $child) {
            $this->generateFillSlots($codewriter, $child);
        }
    }
}
