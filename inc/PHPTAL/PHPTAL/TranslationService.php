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
 */
interface PHPTAL_TranslationService
{
    /**
     * Set the target language for translations.
     *
     * When set to '' no translation will be done.
     *
     * You can specify a list of possible language for exemple :
     *
     * setLanguage('fr_FR', 'fr_FR@euro')
     *
     * @return string - chosen language
     */
    function setLanguage(/*...*/);

    /**
     * PHPTAL will inform translation service what encoding page uses.
     * Output of translate() must be in this encoding.
     */
    function setEncoding($encoding);

    /**
     * Set the domain to use for translations (if different parts of application are translated in different files. This is not for language selection).
     */
    function useDomain($domain);

    /**
     * Set XHTML-escaped value of a variable used in translation key.
     *
     * You should use it to replace all ${key}s with values in translated strings.
     *
     * @param string $key - name of the variable
     * @param string $value_escaped - XHTML markup
     */
    function setVar($key, $value_escaped);

    /**
     * Translate a gettext key and interpolate variables.
     *
     * @param string $key - translation key, e.g. "hello ${username}!"
     * @param string $htmlescape - if true, you should HTML-escape translated string. You should never HTML-escape interpolated variables.
     */
    function translate($key, $htmlescape=true);
}
