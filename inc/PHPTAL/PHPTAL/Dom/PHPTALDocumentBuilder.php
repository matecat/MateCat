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
 * DOM Builder
 *
 * @package PHPTAL
 * @subpackage Dom
 */
class PHPTAL_Dom_PHPTALDocumentBuilder extends PHPTAL_Dom_DocumentBuilder
{
    private $_xmlns;   /* PHPTAL_Dom_XmlnsState */
    private $encoding;

    public function __construct()
    {
        $this->_xmlns = new PHPTAL_Dom_XmlnsState(array(), '');
    }

    public function getResult()
    {
        return $this->documentElement;
    }

    protected function getXmlnsState()
    {
        return $this->_xmlns;
    }

    // ~~~~~ XmlParser implementation ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    public function onDocumentStart()
    {
        $this->documentElement = new PHPTAL_Dom_Element('documentElement', 'http://xml.zope.org/namespaces/tal', array(), $this->getXmlnsState());
        $this->documentElement->setSource($this->file, $this->line);
        $this->_current = $this->documentElement;
    }

    public function onDocumentEnd()
    {
        if (count($this->_stack) > 0) {
            $left='</'.$this->_current->getQualifiedName().'>';
            for ($i = count($this->_stack)-1; $i>0; $i--) $left .= '</'.$this->_stack[$i]->getQualifiedName().'>';
            throw new PHPTAL_ParserException("Not all elements were closed before end of the document. Missing: ".$left,
                        $this->file, $this->line);
        }
    }

    public function onDocType($doctype)
    {
        $this->pushNode(new PHPTAL_Dom_DocumentType($doctype, $this->encoding));
    }

    public function onXmlDecl($decl)
    {
        if (!$this->encoding) {
            throw new PHPTAL_Exception("Encoding not set");
        }
        $this->pushNode(new PHPTAL_Dom_XmlDeclaration($decl, $this->encoding));
    }

    public function onComment($data)
    {
        $this->pushNode(new PHPTAL_Dom_Comment($data, $this->encoding));
    }

    public function onCDATASection($data)
    {
        $this->pushNode(new PHPTAL_Dom_CDATASection($data, $this->encoding));
    }

    public function onProcessingInstruction($data)
    {
        $this->pushNode(new PHPTAL_Dom_ProcessingInstruction($data, $this->encoding));
    }

    public function onElementStart($element_qname, array $attributes)
    {
        $this->_xmlns = $this->_xmlns->newElement($attributes);

        if (preg_match('/^([^:]+):/', $element_qname, $m)) {
            $prefix = $m[1];
            $namespace_uri = $this->_xmlns->prefixToNamespaceURI($prefix);
            if (false === $namespace_uri) {
                throw new PHPTAL_ParserException("There is no namespace declared for prefix of element < $element_qname >. You must have xmlns:$prefix declaration in the same document.",
                            $this->file, $this->line);
            }
        } else {
            $namespace_uri = $this->_xmlns->getCurrentDefaultNamespaceURI();
        }

        $attrnodes = array();
        foreach ($attributes as $qname=>$value) {

            if (preg_match('/^([^:]+):(.+)$/', $qname, $m)) {
                list(,$prefix, $local_name) = $m;
                $attr_namespace_uri = $this->_xmlns->prefixToNamespaceURI($prefix);

            if (false === $attr_namespace_uri) {
                    throw new PHPTAL_ParserException("There is no namespace declared for prefix of attribute $qname of element < $element_qname >. You must have xmlns:$prefix declaration in the same document.",
                            $this->file, $this->line);
            }
            } else {
                $local_name = $qname;
                $attr_namespace_uri = ''; // default NS. Attributes don't inherit namespace per XMLNS spec
            }

            if ($this->_xmlns->isHandledNamespace($attr_namespace_uri)
                && !$this->_xmlns->isValidAttributeNS($attr_namespace_uri, $local_name)) {
                throw new PHPTAL_ParserException("Attribute '$qname' is in '$attr_namespace_uri' namespace, but is not a supported PHPTAL attribute",
                            $this->file, $this->line);
            }

            $attrnodes[] = new PHPTAL_Dom_Attr($qname, $attr_namespace_uri, $value, $this->encoding);
        }

        $node = new PHPTAL_Dom_Element($element_qname, $namespace_uri, $attrnodes, $this->getXmlnsState());
        $this->pushNode($node);
        $this->_stack[] =  $this->_current;
        $this->_current = $node;
    }

    public function onElementData($data)
    {
        $this->pushNode(new PHPTAL_Dom_Text($data, $this->encoding));
    }

    public function onElementClose($qname)
    {
        if ($this->_current === $this->documentElement) {
            throw new PHPTAL_ParserException("Found closing tag for < $qname > where there are no open tags",
                        $this->file, $this->line);
        }
        if ($this->_current->getQualifiedName() != $qname) {
            throw new PHPTAL_ParserException("Tag closure mismatch, expected < /".$this->_current->getQualifiedName()." > (opened in line ".$this->_current->getSourceLine().") but found < /".$qname." >",
                        $this->file, $this->line);
        }
        $this->_current = array_pop($this->_stack);
        if ($this->_current instanceof PHPTAL_Dom_Element) {
            $this->_xmlns = $this->_current->getXmlnsState(); // restore namespace prefixes info to previous state
        }
    }

    private function pushNode(PHPTAL_Dom_Node $node)
    {
        $node->setSource($this->file, $this->line);
        $this->_current->appendChild($node);
    }

    public function setEncoding($encoding)
    {
        $this->encoding = $encoding;
    }
}
