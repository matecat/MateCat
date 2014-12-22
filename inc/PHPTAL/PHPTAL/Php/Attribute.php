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
 * Base class for all PHPTAL attributes.
 *
 * Attributes are first ordered by PHPTAL then called depending on their
 * priority before and after the element printing.
 *
 * An attribute must implements start() and end().
 *
 * @package PHPTAL
 * @subpackage Php
 * @author Laurent Bedubourg <lbedubourg@motion-twin.com>
 */
abstract class PHPTAL_Php_Attribute
{
    const ECHO_TEXT = 'text';
    const ECHO_STRUCTURE = 'structure';

    /** Attribute value specified by the element. */
    protected $expression;

    /** Element using this attribute (PHPTAL's counterpart of XML node) */
    protected $phpelement;

    /**
     * Called before element printing.
     * Default implementation is for backwards compatibility only. Please always override both before() and after().
     */
    public function before(PHPTAL_Php_CodeWriter $codewriter)
    {
        $this->tag = $this->phpelement; $this->phpelement->generator = $codewriter; $this->start(); // FIXME: remove
    }

    /**
     * Called after element printing.
     * Default implementation is for backwards compatibility only. Please always override both before() and after().
     */
    public function after(PHPTAL_Php_CodeWriter $codewriter)
    {
        $this->tag = $this->phpelement; $this->phpelement->generator = $codewriter; $this->end(); // FIXME: remove
    }

    /**
     * for backwards compatibility ONLY. Do not use!
     * @deprecated
     */
    public function start() { throw new PHPTAL_Exception('Do not use'); }

    /**
     * for backwards compatibility ONLY. Do not use!
     * @deprecated
     */
    public function end() { throw new PHPTAL_Exception('Do not use'); }

    /**
     * for backwards compatibility ONLY. Do not use!
     * @deprecated
     */
    final public function doEcho($code) { $this->doEchoAttribute($this->tag->generator, $code); }

    function __construct(PHPTAL_Dom_Element $phpelement, $expression)
    {
        $this->expression = $expression;
        $this->phpelement = $phpelement;
    }

    /**
     * Remove structure|text keyword from expression and stores it for later
     * doEcho() usage.
     *
     * $expression = 'stucture my/path';
     * $expression = $this->extractEchoType($expression);
     *
     * ...
     *
     * $this->doEcho($code);
     */
    protected function extractEchoType($expression)
    {
        $echoType = self::ECHO_TEXT;
        $expression = trim($expression);
        if (preg_match('/^(text|structure)\s+(.*?)$/ism', $expression, $m)) {
            list(, $echoType, $expression) = $m;
        }
        $this->_echoType = strtolower($echoType);
        return trim($expression);
    }

    protected function doEchoAttribute(PHPTAL_Php_CodeWriter $codewriter, $code)
    {
        if ($this->_echoType === self::ECHO_TEXT)
            $codewriter->doEcho($code);
        else
            $codewriter->doEchoRaw($code);
    }

    protected function parseSetExpression($exp)
    {
        $exp = trim($exp);
        // (dest) (value)
        if (preg_match('/^([a-z0-9:\-_]+)\s+(.*?)$/si', $exp, $m)) {
            return array($m[1], trim($m[2]));
        }
        // (dest)
        return array($exp, null);
    }

    protected $_echoType = PHPTAL_Php_Attribute::ECHO_TEXT;
}

