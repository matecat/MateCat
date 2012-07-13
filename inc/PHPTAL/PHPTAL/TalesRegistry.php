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
 * Global registry of TALES expression modifiers
 *
 * @package PHPTAL
 * @subpackage Php
 */
class PHPTAL_TalesRegistry
{
    private static $instance;

    /**
     * This is a singleton
     *
     * @return PHPTAL_TalesRegistry
     */
    static public function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new PHPTAL_TalesRegistry();
        }

        return self::$instance;
    }

    protected function __construct()
    {
        $this->registerPrefix('not', array('PHPTAL_Php_TalesInternal', 'not'));
        $this->registerPrefix('path', array('PHPTAL_Php_TalesInternal', 'path'));
        $this->registerPrefix('string', array('PHPTAL_Php_TalesInternal', 'string'));
        $this->registerPrefix('php', array('PHPTAL_Php_TalesInternal', 'php'));
        $this->registerPrefix('phptal-internal-php-block', array('PHPTAL_Php_TalesInternal', 'phptal_internal_php_block'));
        $this->registerPrefix('exists', array('PHPTAL_Php_TalesInternal', 'exists'));
        $this->registerPrefix('number', array('PHPTAL_Php_TalesInternal', 'number'));
        $this->registerPrefix('true', array('PHPTAL_Php_TalesInternal', 'true'));

        // these are added as fallbacks
        $this->registerPrefix('json', array('PHPTAL_Php_TalesInternal', 'json'), true);
        $this->registerPrefix('urlencode', array('PHPTAL_Php_TalesInternal', 'urlencode'), true);
    }

    /**
     *
     * Expects an either a function name or an array of class and method as
     * callback.
     *
     * @param string $prefix
     * @param mixed $callback
     * @param bool $is_fallback if true, method will be used as last resort (if there's no phptal_tales_foo)
     */
    public function registerPrefix($prefix, $callback, $is_fallback = false)
    {
        if ($this->isRegistered($prefix) && !$this->_callbacks[$prefix]['is_fallback']) {
            if ($is_fallback) {
                return; // simply ignored
            }
            throw new PHPTAL_ConfigurationException("Expression modifier '$prefix' is already registered");
        }

        // Check if valid callback

        if (is_array($callback)) {

            $class = new ReflectionClass($callback[0]);

            if (!$class->isSubclassOf('PHPTAL_Tales')) {
                throw new PHPTAL_ConfigurationException('The class you want to register does not implement "PHPTAL_Tales".');
            }

            $method = new ReflectionMethod($callback[0], $callback[1]);

            if (!$method->isStatic()) {
                throw new PHPTAL_ConfigurationException('The method you want to register is not static.');
            }

            // maybe we want to check the parameters the method takes

        } else {
            if (!function_exists($callback)) {
                throw new PHPTAL_ConfigurationException('The function you are trying to register does not exist.');
            }
        }

        $this->_callbacks[$prefix] = array('callback'=>$callback, 'is_fallback'=>$is_fallback);
    }

    /**
     * true if given prefix is taken
     */
    public function isRegistered($prefix)
    {
        if (array_key_exists($prefix, $this->_callbacks)) {
            return true;
        }
    }

    private function findUnregisteredCallback($typePrefix)
    {
        // class method
        if (strpos($typePrefix, '.')) {
            $classCallback = explode('.', $typePrefix, 2);
            $callbackName  = null;
            if (!is_callable($classCallback, false, $callbackName)) {
                throw new PHPTAL_UnknownModifierException("Unknown phptal modifier $typePrefix. Function $callbackName does not exists or is not statically callable", $typePrefix);
            }
            $ref = new ReflectionClass($classCallback[0]);
            if (!$ref->implementsInterface('PHPTAL_Tales')) {
                throw new PHPTAL_UnknownModifierException("Unable to use phptal modifier $typePrefix as the class $callbackName does not implement the PHPTAL_Tales interface", $typePrefix);
            }
            return $classCallback;
        }

        // check if it is implemented via code-generating function
        $func = 'phptal_tales_'.str_replace('-', '_', $typePrefix);
        if (function_exists($func)) {
            return $func;
        }

        // The following code is automatically modified in version for PHP 5.3
        $func = 'PHPTALNAMESPACE\\phptal_tales_'.str_replace('-', '_', $typePrefix);
        if (function_exists($func)) {
            return $func;
        }

        return NULL;
    }

    /**
     * get callback for the prefix
     *
     * @return callback or NULL
     */
    public function getCallback($prefix)
    {
        if ($this->isRegistered($prefix) && !$this->_callbacks[$prefix]['is_fallback']) {
            return $this->_callbacks[$prefix]['callback'];
        }

        if ($callback = $this->findUnregisteredCallback($prefix)) {
            return $callback;
        }

        if ($this->isRegistered($prefix)) {
            return $this->_callbacks[$prefix]['callback'];
        }

        return NULL;
    }

    /**
     * {callback, bool is_fallback}
     */
    private $_callbacks = array();
}

