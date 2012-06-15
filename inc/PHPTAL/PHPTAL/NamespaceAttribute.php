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
 * Information about TAL attributes (in which order they are executed and how they generate the code)
 *
 * From http://dev.zope.org/Wikis/DevSite/Projects/ZPT/TAL%20Specification%201.4
 *
 * Order of Operations
 *
 * When there is only one TAL statement per element, the order in which
 * they are executed is simple. Starting with the root element, each
 * element's statements are executed, then each of its child elements is
 * visited, in order, to do the same.
 *
 * Any combination of statements may appear on the same elements, except
 * that the content and replace statements may not appear together.
 *
 * When an element has multiple statements, they are executed in this
 * order:
 *
 *     * define
 *     * condition
 *     * repeat
 *     * content or replace
 *     * attributes
 *     * omit-tag
 *
 * Since the on-error statement is only invoked when an error occurs, it
 * does not appear in the list.
 *
 * The reasoning behind this ordering goes like this: You often want to set
 * up variables for use in other statements, so define comes first. The
 * very next thing to do is decide whether this element will be included at
 * all, so condition is next; since the condition may depend on variables
 * you just set, it comes after define. It is valuable be able to replace
 * various parts of an element with different values on each iteration of a
 * repeat, so repeat is next. It makes no sense to replace attributes and
 * then throw them away, so attributes is last. The remaining statements
 * clash, because they each replace or edit the statement element.
 *
 * If you want to override this ordering, you must do so by enclosing the
 * element in another element, possibly div or span, and placing some of
 * the statements on this new element.
 *
 *
 * @package PHPTAL
 * @subpackage Namespace
 */
abstract class PHPTAL_NamespaceAttribute
{
    /** Attribute name without the namespace: prefix */
    private $local_name;

    /** [0 - 1000] */
    private $_priority;

    /** PHPTAL_Namespace */
    private $_namespace;

    /**
     * @param string $name The attribute name
     * @param int $priority Attribute execution priority
     */
    public function __construct($local_name, $priority)
    {
        $this->local_name = $local_name;
        $this->_priority = $priority;
    }

    /**
     * @return string
     */
    public function getLocalName()
    {
        return $this->local_name;
    }

    public function getPriority() { return $this->_priority; }
    public function getNamespace() { return $this->_namespace; }
    public function setNamespace(PHPTAL_Namespace $ns) { $this->_namespace = $ns; }

    public function createAttributeHandler(PHPTAL_Dom_Element $tag, $expression)
    {
        return $this->_namespace->createAttributeHandler($this, $tag, $expression);
    }
}
