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
 * @package PHPTAL
 * @subpackage Php
 */
class PHPTAL_Php_State
{
    private $debug      = false;
    private $tales_mode = 'tales';
    private $encoding;
    private $output_mode;
    private $phptal;

    function __construct(PHPTAL $phptal)
    {
        $this->phptal = $phptal;
        $this->encoding = $phptal->getEncoding();
        $this->output_mode = $phptal->getOutputMode();
    }

    /**
     * used by codewriter to get information for phptal:cache
     */
    public function getCacheFilesBaseName()
    {
        return $this->phptal->getCodePath();
    }

    /**
     * true if PHPTAL has translator set
     */
    public function isTranslationOn()
    {
        return !!$this->phptal->getTranslator();
    }

    /**
     * controlled by phptal:debug
     */
    public function setDebug($bool)
    {
        $old = $this->debug;
        $this->debug = $bool;
        return $old;
    }

    /**
     * if true, add additional diagnostic information to generated code
     */
    public function isDebugOn()
    {
        return $this->debug;
    }

    /**
     * Sets new and returns old TALES mode.
     * Valid modes are 'tales' and 'php'
     *
     * @param string $mode
     *
     * @return string
     */
    public function setTalesMode($mode)
    {
        $old = $this->tales_mode;
        $this->tales_mode = $mode;
        return $old;
    }

    public function getTalesMode()
    {
        return $this->tales_mode;
    }

    /**
     * encoding used for both template input and output
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * Syntax rules to follow in generated code
     *
     * @return one of PHPTAL::XHTML, PHPTAL::XML, PHPTAL::HTML5
     */
    public function getOutputMode()
    {
        return $this->output_mode;
    }

    /**
     * Load prefilter
     */
    public function getPreFilterByName($name)
    {
        return $this->phptal->getPreFilterByName($name);
    }

    /**
     * compile TALES expression according to current talesMode
     * @return string with PHP code or array with expressions for TalesChainExecutor
     */
    public function evaluateExpression($expression)
    {
        if ($this->getTalesMode() === 'php') {
            return PHPTAL_Php_TalesInternal::php($expression);
        }
        return PHPTAL_Php_TalesInternal::compileToPHPExpressions($expression, false);
    }

    /**
     * compile TALES expression according to current talesMode
     * @return string with PHP code
     */
    private function compileTalesToPHPExpression($expression)
    {
        if ($this->getTalesMode() === 'php') {
            return PHPTAL_Php_TalesInternal::php($expression);
        }
        return PHPTAL_Php_TalesInternal::compileToPHPExpression($expression, false);
    }

    /**
     * returns PHP code that generates given string, including dynamic replacements
     *
     * It's almost unused.
     */
    public function interpolateTalesVarsInString($string)
    {
        return PHPTAL_Php_TalesInternal::parseString($string, false, ($this->getTalesMode() === 'tales') ? '' : 'php:' );
    }

    /**
     * replaces ${} in string, expecting HTML-encoded input and HTML-escapes output
     */
    public function interpolateTalesVarsInHTML($src)
    {
        return preg_replace_callback('/((?:\$\$)*)\$\{(structure |text )?(.*?)\}|((?:\$\$)+)\{/isS',
                                     array($this,'_interpolateTalesVarsInHTMLCallback'), $src);
    }

    /**
     * callback for interpolating TALES with HTML-escaping
     */
    private function _interpolateTalesVarsInHTMLCallback($matches)
    {
        return $this->_interpolateTalesVarsCallback($matches, 'html');
    }

    /**
     * replaces ${} in string, expecting CDATA (basically unescaped) input,
     * generates output protected against breaking out of CDATA in XML/HTML
     * (depending on current output mode).
     */
    public function interpolateTalesVarsInCDATA($src)
    {
        return preg_replace_callback('/((?:\$\$)*)\$\{(structure |text )?(.*?)\}|((?:\$\$)+)\{/isS',
                                     array($this,'_interpolateTalesVarsInCDATACallback'), $src);
    }

    /**
     * callback for interpolating TALES with CDATA escaping
     */
    private function _interpolateTalesVarsInCDATACallback($matches)
    {
        return $this->_interpolateTalesVarsCallback($matches, 'cdata');
    }

    private function _interpolateTalesVarsCallback($matches, $format)
    {
        // replaces $${ with literal ${ (or $$$${ with $${ etc)
        if (!empty($matches[4])) {
            return substr($matches[4], strlen($matches[4])/2).'{';
        }

        // same replacement, but before executed expression
        $dollars = substr($matches[1], strlen($matches[1])/2);

        $code = $matches[3];
        if ($format == 'html') {
            $code = html_entity_decode($code, ENT_QUOTES, $this->getEncoding());
        }

        $code = $this->compileTalesToPHPExpression($code);

        if (rtrim($matches[2]) == 'structure') { // regex captures a space there
            return $dollars.'<?php echo '.$this->stringify($code)." ?>\n";
        } else {
            if ($format == 'html') {
                return $dollars.'<?php echo '.$this->htmlchars($code)." ?>\n";
            }
            if ($format == 'cdata') {
                // quite complex for an "unescaped" section, isn't it?
                if ($this->getOutputMode() === PHPTAL::HTML5) {
                    return $dollars."<?php echo str_replace('</','<\\\\/', ".$this->stringify($code).") ?>\n";
                } elseif ($this->getOutputMode() === PHPTAL::XHTML) {
                    // both XML and HMTL, because people will inevitably send it as text/html :(
                    return $dollars."<?php echo strtr(".$this->stringify($code)." ,array(']]>'=>']]]]><![CDATA[>','</'=>'<\\/')) ?>\n";
                } else {
                    return $dollars."<?php echo str_replace(']]>',']]]]><![CDATA[>', ".$this->stringify($code).") ?>\n";
                }
            }
            assert(0);
        }
    }

    /**
     * expects PHP code and returns PHP code that will generate escaped string
     * Optimizes case when PHP string is given.
     *
     * @return php code
     */
    public function htmlchars($php)
    {
        // PHP strings can be escaped at compile time
        if (preg_match('/^\'((?:[^\'{]+|\\\\.)*)\'$/s', $php, $m)) {
            return "'".htmlspecialchars(str_replace('\\\'', "'", $m[1]), ENT_QUOTES)."'";
        }
        return 'phptal_escape('.$php.')';
    }

    /**
     * allow proper printing of any object
     * (without escaping - for use with structure keyword)
     *
     * @return php code
     */
    public function stringify($php)
    {
        // PHP strings don't need to be changed
        if (preg_match('/^\'(?>[^\'\\\\]+|\\\\.)*\'$|^\s*"(?>[^"\\\\]+|\\\\.)*"\s*$/s', $php)) {
            return $php;
        }
        return 'phptal_tostring('.$php.')';
    }
}

