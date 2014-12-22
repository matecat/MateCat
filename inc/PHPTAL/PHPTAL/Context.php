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
 * This class handles template execution context.
 * Holds template variables and carries state/scope across macro executions.
 *
 */
class PHPTAL_Context
{
    public $repeat;
    public $_xmlDeclaration;
    public $_docType;
    private $_nothrow;
    private $_slots = array();
    private $_slotsStack = array();
    private $_parentContext = null;
    private $_globalContext = null;
    private $_echoDeclarations = false;

    public function __construct()
    {
        $this->repeat = new stdClass();
    }

    public function __clone()
    {
        $this->repeat = clone $this->repeat;
    }

    /**
     * will switch to this context when popContext() is called
     *
     * @return void
     */
    public function setParent(PHPTAL_Context $parent)
    {
        $this->_parentContext = $parent;
    }

    /**
     * set stdClass object which has property of every global variable
     * It can use __isset() and __get() [none of them or both]
     *
     * @return void
     */
    public function setGlobal(stdClass $globalContext)
    {
        $this->_globalContext = $globalContext;
    }

    /**
     * save current execution context
     *
     * @return Context (new)
     */
    public function pushContext()
    {
        $res = clone $this;
        $res->setParent($this);
        return $res;
    }

    /**
     * get previously saved execution context
     *
     * @return Context (old)
     */
    public function popContext()
    {
        return $this->_parentContext;
    }

    /**
     * @param bool $tf true if DOCTYPE and XML declaration should be echoed immediately, false if buffered
     */
    public function echoDeclarations($tf)
    {
        $this->_echoDeclarations = $tf;
    }

    /**
     * Set output document type if not already set.
     *
     * This method ensure PHPTAL uses the first DOCTYPE encountered (main
     * template or any macro template source containing a DOCTYPE.
     *
     * @param bool $called_from_macro will do nothing if _echoDeclarations is also set
     *
     * @return void
     */
    public function setDocType($doctype,$called_from_macro)
    {
        // FIXME: this is temporary workaround for problem of DOCTYPE disappearing in cloned PHPTAL object (because clone keeps _parentContext)
        if (!$this->_docType) {
            $this->_docType = $doctype;
        }

        if ($this->_parentContext) {
            $this->_parentContext->setDocType($doctype, $called_from_macro);
        } else if ($this->_echoDeclarations) {
            if (!$called_from_macro) {
                echo $doctype;
            } else {
                throw new PHPTAL_ConfigurationException("Executed macro in file with DOCTYPE when using echoExecute(). This is not supported yet. Remove DOCTYPE or use PHPTAL->execute().");
            }
        }
        else if (!$this->_docType) {
            $this->_docType = $doctype;
        }
    }

    /**
     * Set output document xml declaration.
     *
     * This method ensure PHPTAL uses the first xml declaration encountered
     * (main template or any macro template source containing an xml
     * declaration)
     *
     * @param bool $called_from_macro will do nothing if _echoDeclarations is also set
     *
     * @return void
     */
    public function setXmlDeclaration($xmldec, $called_from_macro)
    {
        // FIXME
        if (!$this->_xmlDeclaration) {
            $this->_xmlDeclaration = $xmldec;
        }

        if ($this->_parentContext) {
            $this->_parentContext->setXmlDeclaration($xmldec, $called_from_macro);
        } else if ($this->_echoDeclarations) {
            if (!$called_from_macro) {
                echo $xmldec."\n";
            } else {
                throw new PHPTAL_ConfigurationException("Executed macro in file with XML declaration when using echoExecute(). This is not supported yet. Remove XML declaration or use PHPTAL->execute().");
            }
        } else if (!$this->_xmlDeclaration) {
            $this->_xmlDeclaration = $xmldec;
        }
    }

    /**
     * Activate or deactivate exception throwing during unknown path
     * resolution.
     *
     * @return void
     */
    public function noThrow($bool)
    {
        $this->_nothrow = $bool;
    }

    /**
     * Returns true if specified slot is filled.
     *
     * @return bool
     */
    public function hasSlot($key)
    {
        return isset($this->_slots[$key]) || ($this->_parentContext && $this->_parentContext->hasSlot($key));
    }

    /**
     * Returns the content of specified filled slot.
     *
     * Use echoSlot() whenever you just want to output the slot
     *
     * @return string
     */
    public function getSlot($key)
    {
        if (isset($this->_slots[$key])) {
            if (is_string($this->_slots[$key])) {
                return $this->_slots[$key];
            }
            ob_start();
            call_user_func($this->_slots[$key][0], $this->_slots[$key][1], $this->_slots[$key][2]);
            return ob_get_clean();
        } else if ($this->_parentContext) {
            return $this->_parentContext->getSlot($key);
        }
    }

    /**
     * Immediately echoes content of specified filled slot.
     *
     * Equivalent of echo $this->getSlot();
     *
     * @return string
     */
    public function echoSlot($key)
    {
        if (isset($this->_slots[$key])) {
            if (is_string($this->_slots[$key])) {
                echo $this->_slots[$key];
            } else {
                call_user_func($this->_slots[$key][0], $this->_slots[$key][1], $this->_slots[$key][2]);
            }
        } else if ($this->_parentContext) {
            return $this->_parentContext->echoSlot($key);
        }
    }

    /**
     * Fill a macro slot.
     *
     * @return void
     */
    public function fillSlot($key, $content)
    {
        $this->_slots[$key] = $content;
        if ($this->_parentContext) {
            // Works around bug with tal:define popping context after fillslot
            $this->_parentContext->_slots[$key] = $content;
        }
    }

    public function fillSlotCallback($key, $callback, $_thistpl, $tpl)
    {
        assert('is_callable($callback)');
        $this->_slots[$key] = array($callback, $_thistpl, $tpl);
        if ($this->_parentContext) {
            // Works around bug with tal:define popping context after fillslot
            $this->_parentContext->_slots[$key] = array($callback, $_thistpl, $tpl);
        }
    }

    /**
     * Push current filled slots on stack.
     *
     * @return void
     */
    public function pushSlots()
    {
        $this->_slotsStack[] =  $this->_slots;
        $this->_slots = array();
    }

    /**
     * Restore filled slots stack.
     *
     * @return void
     */
    public function popSlots()
    {
        $this->_slots = array_pop($this->_slotsStack);
    }

    /**
     * Context setter.
     *
     * @return void
     */
    public function __set($varname, $value)
    {
        if (preg_match('/^_|\s/', $varname)) {
            throw new PHPTAL_InvalidVariableNameException('Template variable error \''.$varname.'\' must not begin with underscore or contain spaces');
        }
        $this->$varname = $value;
    }

    /**
     * @return bool
     */
    public function __isset($varname)
    {
        // it doesn't need to check isset($this->$varname), because PHP does that _before_ calling __isset()
        return isset($this->_globalContext->$varname) || defined($varname);
    }

    /**
     * Context getter.
     * If variable doesn't exist, it will throw an exception, unless noThrow(true) has been called
     *
     * @return mixed
     */
    public function __get($varname)
    {
        // PHP checks public properties first, there's no need to support them here

        // must use isset() to allow custom global contexts with __isset()/__get()
        if (isset($this->_globalContext->$varname)) {
            return $this->_globalContext->$varname;
        }

        if (defined($varname)) {
            return constant($varname);
        }

        if ($this->_nothrow) {
            return null;
        }

        throw new PHPTAL_VariableNotFoundException("Unable to find variable '$varname' in current scope");
    }

    /**
     * helper method for PHPTAL_Context::path()
     *
     * @access private
     */
    private static function pathError($base, $path, $current, $basename)
    {
        if ($current !== $path) {
            $pathinfo = " (in path '.../$path')";
        } else $pathinfo = '';

        if (!empty($basename)) {
            $basename = "'" . $basename . "' ";
        }

        if (is_array($base)) {
            throw new PHPTAL_VariableNotFoundException("Array {$basename}doesn't have key named '$current'$pathinfo");
        }
        if (is_object($base)) {
            throw new PHPTAL_VariableNotFoundException(ucfirst(get_class($base))." object {$basename}doesn't have method/property named '$current'$pathinfo");
        }
        throw new PHPTAL_VariableNotFoundException(trim("Attempt to read property '$current'$pathinfo from ".gettype($base)." value {$basename}"));
    }

    /**
     * Resolve TALES path starting from the first path element.
     * The TALES path : object/method1/10/method2
     * will call : $ctx->path($ctx->object, 'method1/10/method2')
     *
     * This function is very important for PHPTAL performance.
     *
     * This function will become non-static in the future
     *
     * @param mixed  $base    first element of the path ($ctx)
     * @param string $path    rest of the path
     * @param bool   $nothrow is used by phptal_exists(). Prevents this function from
     * throwing an exception when a part of the path cannot be resolved, null is
     * returned instead.
     *
     * @access private
     * @return mixed
     */
    public static function path($base, $path, $nothrow=false)
    {
        if ($base === null) {
            if ($nothrow) return null;
            PHPTAL_Context::pathError($base, $path, $path, $path);
        }

        $chunks  = explode('/', $path);
        $current = null;

        for ($i = 0; $i < count($chunks); $i++) {
            $prev    = $current;
            $current = $chunks[$i];

            // object handling
            if (is_object($base)) {
                // look for method. Both method_exists and is_callable are required because of __call() and protected methods
                if (method_exists($base, $current) && is_callable(array($base, $current))) {
                    $base = $base->$current();
                    continue;
                }

                // look for property
                if (property_exists($base, $current)) {
                    $base = $base->$current;
                    continue;
                }

                if ($base instanceof ArrayAccess && $base->offsetExists($current)) {
                    $base = $base->offsetGet($current);
                    continue;
                }

                if (($current === 'length' || $current === 'size') && $base instanceof Countable) {
                    $base = count($base);
                    continue;
                }

                // look for isset (priority over __get)
                if (method_exists($base, '__isset')) {
                    if ($base->__isset($current)) {
                        $base = $base->$current;
                        continue;
                    }
                }
                // ask __get and discard if it returns null
                elseif (method_exists($base, '__get')) {
                    $tmp = $base->$current;
                    if (null !== $tmp) {
                        $base = $tmp;
                        continue;
                    }
                }

                // magic method call
                if (method_exists($base, '__call')) {
                    try
                    {
                        $base = $base->__call($current, array());
                        continue;
                    }
                    catch(BadMethodCallException $e) {}
                }

                if ($nothrow) {
                    return null;
                }

                PHPTAL_Context::pathError($base, $path, $current, $prev);
            }

            // array handling
            if (is_array($base)) {
                // key or index
                if (array_key_exists((string)$current, $base)) {
                    $base = $base[$current];
                    continue;
                }

                // virtual methods provided by phptal
                if ($current == 'length' || $current == 'size') {
                    $base = count($base);
                    continue;
                }

                if ($nothrow)
                    return null;

                PHPTAL_Context::pathError($base, $path, $current, $prev);
            }

            // string handling
            if (is_string($base)) {
                // virtual methods provided by phptal
                if ($current == 'length' || $current == 'size') {
                    $base = strlen($base);
                    continue;
                }

                // access char at index
                if (is_numeric($current)) {
                    $base = $base[$current];
                    continue;
                }
            }

            // if this point is reached, then the part cannot be resolved

            if ($nothrow)
                return null;

            PHPTAL_Context::pathError($base, $path, $current, $prev);
        }

        return $base;
    }
}

/**
 * @see PHPTAL_Context::path()
 * @deprecated
 */
function phptal_path($base, $path, $nothrow=false)
{
    return PHPTAL_Context::path($base, $path, $nothrow);
}

/**
 * helper function for chained expressions
 *
 * @param mixed $var value to check
 * @return bool
 * @access private
 */
function phptal_isempty($var)
{
    return $var === null || $var === false || $var === ''
           || ((is_array($var) || $var instanceof Countable) && count($var)===0);
}

/**
 * helper function for conditional expressions
 *
 * @param mixed $var value to check
 * @return bool
 * @access private
 */
function phptal_true($var)
{
    return $var && (!$var instanceof Countable || count($var));
}

/**
 * convert to string and html-escape given value (of any type)
 *
 * @access private
 */
function phptal_escape($var)
{
    if (is_string($var)) {
        return htmlspecialchars($var, ENT_QUOTES);
    }
    return htmlspecialchars(phptal_tostring($var), ENT_QUOTES);
}

/**
 * convert anything to string
 *
 * @access private
 */
function phptal_tostring($var)
{
    if (is_string($var)) {
        return $var;
    } elseif (is_bool($var)) {
        return (int)$var;
    } elseif (is_array($var)) {
        return implode(', ', array_map('phptal_tostring', $var));
    } elseif ($var instanceof SimpleXMLElement) {

        /* There is no sane way to tell apart element and attribute nodes
           in SimpleXML, so here's a guess that if something has no attributes
           or children, and doesn't output <, then it's an attribute */

        $xml = $var->asXML();
        if ($xml[0] === '<' || $var->attributes() || $var->children()) {
            return $xml;
        }
    }
    return (string)$var;
}
