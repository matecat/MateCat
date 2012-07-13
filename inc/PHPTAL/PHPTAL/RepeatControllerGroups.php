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
 * @author   Iván Montes <drslump@pollinimini.net>
 * @license  http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @version  SVN: $Id$
 * @link     http://phptal.org/
 */

/**
 * Keeps track of variable contents when using grouping in a path (first/ and last/)
 *
 * @package PHPTAL
 * @subpackage Php
 */
class PHPTAL_RepeatControllerGroups
{
    protected $dict = array();
    protected $cache = array();
    protected $data = null;
    protected $vars = array();
    protected $branch;


    public function __construct()
    {
        $this->dict = array();
        $this->reset();
    }

    /**
     * Resets the result caches. Use it to signal an iteration in the loop
     *
     */
    public function reset()
    {
        $this->cache = array();
    }

    /**
     * Checks if the data passed is the first one in a group
     *
     * @param mixed $data   The data to evaluate
     *
     * @return Mixed    True if the first item in the group, false if not and
     *                  this same object if the path is not finished
     */
    public function first($data)
    {
        if ( !is_array($data) && !is_object($data) && !is_null($data) ) {

            if ( !isset($this->cache['F']) ) {

                $hash = md5($data);

                if ( !isset($this->dict['F']) || $this->dict['F'] !== $hash ) {
                    $this->dict['F'] = $hash;
                    $res = true;
                } else {
                    $res = false;
                }

                $this->cache['F'] = $res;
            }

            return $this->cache['F'];
        }

        $this->data = $data;
        $this->branch = 'F';
        $this->vars = array();
        return $this;
    }

    /**
     * Checks if the data passed is the last one in a group
     *
     * @param mixed $data   The data to evaluate
     *
     * @return Mixed    True if the last item in the group, false if not and
     *                  this same object if the path is not finished
     */
    public function last($data)
    {
        if ( !is_array($data) && !is_object($data) && !is_null($data) ) {

            if ( !isset($this->cache['L']) ) {

                $hash = md5($data);

                if (empty($this->dict['L'])) {
                    $this->dict['L'] = $hash;
                    $res = false;
                } elseif ($this->dict['L'] !== $hash) {
                    $this->dict['L'] = $hash;
                    $res = true;
                } else {
                    $res = false;
                }

                $this->cache['L'] = $res;
            }

            return $this->cache['L'];
        }

        $this->data = $data;
        $this->branch = 'L';
        $this->vars = array();
        return $this;
    }

    /**
     * Handles variable accesses for the tal path resolver
     *
     * @param string $var   The variable name to check
     *
     * @return Mixed    An object/array if the path is not over or a boolean
     *
     * @todo    replace the PHPTAL_Context::path() with custom code
     */
    public function __get($var)
    {
        // When the iterator item is empty we just let the tal
        // expression consume by continuously returning this
        // same object which should evaluate to true for 'last'
        if ( is_null($this->data) ) {
            return $this;
        }

        // Find the requested variable
        $value = PHPTAL_Context::path($this->data, $var, true);

        // Check if it's an object or an array
        if ( is_array($value) || is_object($value) ) {
            // Move the context to the requested variable and return
            $this->data = $value;
            $this->addVarName($var);
            return $this;
        }

        // get a hash of the variable contents
        $hash = md5($value);

        // compute a path for the variable to use as dictionary key
        $path = $this->branch . $this->getVarPath() . $var;

        // If we don't know about this var store in the dictionary
        if ( !isset($this->cache[$path]) ) {

            if ( !isset($this->dict[$path]) ) {
                $this->dict[$path] = $hash;
                $res = $this->branch === 'F';
            } else {
                // Check if the value has changed
                if ($this->dict[$path] !== $hash) {
                    $this->dict[$path] = $hash;
                    $res = true;
                } else {
                    $res = false;
                }
            }

            $this->cache[$path] = $res;
        }

        return $this->cache[$path];

    }

    /**
     * Adds a variable name to the current path of variables
     *
     * @param string $varname  The variable name to store as a path part
     * @access protected
     */
    protected function addVarName($varname)
    {
        $this->vars[] = $varname;
    }

    /**
     * Returns the current variable path separated by a slash
     *
     * @return String  The current variable path
     * @access protected
     */
    protected function getVarPath()
    {
        return implode('/', $this->vars) . '/';
    }
}
