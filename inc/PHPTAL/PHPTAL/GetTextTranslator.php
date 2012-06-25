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
 * PHPTAL_TranslationService gettext implementation.
 *
 * Because gettext is the most common translation library in use, this
 * implementation is shipped with the PHPTAL library.
 *
 * Please refer to the PHPTAL documentation for usage examples.
 *
 * @package PHPTAL
 * @author Laurent Bedubourg <lbedubourg@motion-twin.com>
 */
class PHPTAL_GetTextTranslator implements PHPTAL_TranslationService
{
    private $_vars = array();
    private $_currentDomain;
    private $_encoding = 'UTF-8';
    private $_canonicalize = false;

    public function __construct()
    {
        if (!function_exists('gettext')) throw new PHPTAL_ConfigurationException("Gettext not installed");
        $this->useDomain("messages"); // PHP bug #21965
    }

    /**
     * set encoding that is used by template and is expected from gettext
     * the default is UTF-8
     *
     * @param string $enc encoding name
     */
    public function setEncoding($enc)
    {
        $this->_encoding = $enc;
    }

    /**
     * if true, all non-ASCII characters in keys will be converted to C<xxx> form. This impacts performance.
     * by default keys will be passed to gettext unmodified.
     *
     * This function is only for backwards compatibility
     *
     * @param bool $bool enable old behavior
     */
    public function setCanonicalize($bool)
    {
        $this->_canonicalize = $bool;
    }

    /**
     * It expects locale names as arguments.
     * Choses first one that works.
     *
     * setLanguage("en_US.utf8","en_US","en_GB","en")
     *
     * @return string - chosen language
     */
    public function setLanguage(/*...*/)
    {
        $langs = func_get_args();

        $langCode = $this->trySettingLanguages(LC_ALL, $langs);
        if ($langCode) return $langCode;

        if (defined("LC_MESSAGES")) {
            $langCode = $this->trySettingLanguages(LC_MESSAGES, $langs);
            if ($langCode) return $langCode;
        }

        throw new PHPTAL_ConfigurationException('Language(s) code(s) "'.implode(', ', $langs).'" not supported by your system');
    }

    private function trySettingLanguages($category, array $langs)
    {
        foreach ($langs as $langCode) {
            putenv("LANG=$langCode");
            putenv("LC_ALL=$langCode");
            putenv("LANGUAGE=$langCode");
            if (setlocale($category, $langCode)) {
                return $langCode;
            }
        }
        return null;
    }

    /**
     * Adds translation domain (usually it's the same as name of .po file [without extension])
     *
     * Encoding must be set before calling addDomain!
     */
    public function addDomain($domain, $path='./locale/')
    {
        bindtextdomain($domain, $path);
        if ($this->_encoding) {
            bind_textdomain_codeset($domain, $this->_encoding);
        }
        $this->useDomain($domain);
    }

    /**
     * Switches to one of the domains previously set via addDomain()
     *
     * @param string $domain name of translation domain to be used.
     *
     * @return string - old domain
     */
    public function useDomain($domain)
    {
        $old = $this->_currentDomain;
        $this->_currentDomain = $domain;
        textdomain($domain);
        return $old;
    }

    /**
     * used by generated PHP code. Don't use directly.
     */
    public function setVar($key, $value)
    {
        $this->_vars[$key] = $value;
    }

    /**
     * translate given key.
     *
     * @param bool $htmlencode if true, output will be HTML-escaped.
     */
    public function translate($key, $htmlencode=true)
    {
        if ($this->_canonicalize) $key = self::_canonicalizeKey($key);

        $value = gettext($key);

        if ($htmlencode) {
            $value = htmlspecialchars($value, ENT_QUOTES);
        }
        while (preg_match('/\${(.*?)\}/sm', $value, $m)) {
            list($src, $var) = $m;
            if (!array_key_exists($var, $this->_vars)) {
                throw new PHPTAL_VariableNotFoundException('Interpolation error. Translation uses ${'.$var.'}, which is not defined in the template (via i18n:name)');
            }
            $value = str_replace($src, $this->_vars[$var], $value);
        }
        return $value;
    }

    /**
     * For backwards compatibility only.
     */
    private static function _canonicalizeKey($key_)
    {
        $result = "";
        $key_ = trim($key_);
        $key_ = str_replace("\n", "", $key_);
        $key_ = str_replace("\r", "", $key_);
        for ($i = 0; $i<strlen($key_); $i++) {
            $c = $key_[$i];
            $o = ord($c);
            if ($o < 5 || $o > 127) {
                $result .= 'C<'.$o.'>';
            } else {
                $result .= $c;
            }
        }
        return $result;
    }
}

