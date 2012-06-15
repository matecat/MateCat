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
 * ZPTInternationalizationSupport
 *
 * i18n:translate
 *
 * This attribute is used to mark units of text for translation. If this
 * attribute is specified with an empty string as the value, the message ID
 * is computed from the content of the element bearing this attribute.
 * Otherwise, the value of the element gives the message ID.
 *
 *
 * @package PHPTAL
 * @subpackage Php.attribute.i18n
 */
class PHPTAL_Php_Attribute_I18N_Translate extends PHPTAL_Php_Attribute_TAL_Content
{
    public function before(PHPTAL_Php_CodeWriter $codewriter)
    {
        $escape = true;
        $this->_echoType = PHPTAL_Php_Attribute::ECHO_TEXT;
        if (preg_match('/^(text|structure)(?:\s+(.*)|\s*$)/', $this->expression, $m)) {
            if ($m[1]=='structure') { $escape=false; $this->_echoType = PHPTAL_Php_Attribute::ECHO_STRUCTURE; }
            $this->expression = isset($m[2])?$m[2]:'';
        }

        $this->_prepareNames($codewriter, $this->phpelement);

        // if no expression is given, the content of the node is used as
        // a translation key
        if (strlen(trim($this->expression)) == 0) {
            $key = $this->_getTranslationKey($this->phpelement, !$escape, $codewriter->getEncoding());
            $key = trim(preg_replace('/\s+/sm'.($codewriter->getEncoding()=='UTF-8'?'u':''), ' ', $key));
            if ('' === trim($key)) {
                throw new PHPTAL_TemplateException("Empty translation key",
                            $this->phpelement->getSourceFile(), $this->phpelement->getSourceLine());
            }
            $code = $codewriter->str($key);
        } else {
            $code = $codewriter->evaluateExpression($this->expression);
            if (is_array($code))
                return $this->generateChainedContent($codewriter, $code);

            $code = $codewriter->evaluateExpression($this->expression);
        }

        $codewriter->pushCode('echo '.$codewriter->getTranslatorReference().'->translate('.$code.','.($escape ? 'true':'false').');');
    }

    public function after(PHPTAL_Php_CodeWriter $codewriter)
    {
    }

    public function talesChainPart(PHPTAL_Php_TalesChainExecutor $executor, $exp, $islast)
    {
        $codewriter = $executor->getCodeWriter();

        $escape = !($this->_echoType == PHPTAL_Php_Attribute::ECHO_STRUCTURE);
        $exp = $codewriter->getTranslatorReference()."->translate($exp, " . ($escape ? 'true':'false') . ')';
        if (!$islast) {
            $var = $codewriter->createTempVariable();
            $executor->doIf('!phptal_isempty('.$var.' = '.$exp.')');
            $codewriter->pushCode("echo $var");
            $codewriter->recycleTempVariable($var);
        } else {
            $executor->doElse();
            $codewriter->pushCode("echo $exp");
        }
    }

    private function _getTranslationKey(PHPTAL_Dom_Node $tag, $preserve_tags, $encoding)
    {
        $result = '';
        foreach ($tag->childNodes as $child) {
            if ($child instanceof PHPTAL_Dom_Text) {
                if ($preserve_tags) {
                    $result .= $child->getValueEscaped();
                } else {
                    $result .= $child->getValue($encoding);
                }
            } elseif ($child instanceof PHPTAL_Dom_Element) {
                if ($attr = $child->getAttributeNodeNS('http://xml.zope.org/namespaces/i18n', 'name')) {
                    $result .= '${' . $attr->getValue() . '}';
                } else {

                    if ($preserve_tags) {
                        $result .= '<'.$child->getQualifiedName();
                        foreach ($child->getAttributeNodes() as $attr) {
                            if ($attr->getReplacedState() === PHPTAL_Dom_Attr::HIDDEN) continue;

                            $result .= ' '.$attr->getQualifiedName().'="'.$attr->getValueEscaped().'"';
                        }
                        $result .= '>'.$this->_getTranslationKey($child, $preserve_tags, $encoding) . '</'.$child->getQualifiedName().'>';
                    } else {
                        $result .= $this->_getTranslationKey($child, $preserve_tags, $encoding);
                    }
                }
            }
        }
        return $result;
    }

    private function _prepareNames(PHPTAL_Php_CodeWriter $codewriter, PHPTAL_Dom_Node $tag)
    {
        foreach ($tag->childNodes as $child) {
            if ($child instanceof PHPTAL_Dom_Element) {
                if ($child->hasAttributeNS('http://xml.zope.org/namespaces/i18n', 'name')) {
                    $child->generateCode($codewriter);
                } else {
                    $this->_prepareNames($codewriter, $child);
                }
            }
        }
    }
}

