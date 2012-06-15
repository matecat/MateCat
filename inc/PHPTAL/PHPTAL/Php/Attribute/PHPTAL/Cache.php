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
 *  phptal:cache (note that's not tal:cache) caches element's HTML for a given time. Time is a number with 'd', 'h', 'm' or 's' suffix.
 *  There's optional parameter that defines how cache should be shared. By default cache is not sensitive to template's context at all
 *  - it's shared between all pages that use that template.
 *  You can add per url to have separate copy of given element for every URL.
 *
 *  You can add per expression to have different cache copy for every different value of an expression (which MUST evaluate to a string).
 *  Expression cannot refer to variables defined using tal:define on the same element.
 *
 *  NB:
 *  * phptal:cache blocks can be nested, but outmost block will cache other blocks regardless of their freshness.
 *  * you cannot use metal:fill-slot inside elements with phptal:cache
 *
 *  Examples:
 *  <div phptal:cache="3h">...</div> <!-- <div> to be evaluated at most once per 3 hours. -->
 *  <ul phptal:cache="1d per object/id">...</ul> <!-- <ul> be cached for one day, separately for each object. -->
 *
 * @package PHPTAL
 * @subpackage Php.attribute.phptal
*/
class PHPTAL_Php_Attribute_PHPTAL_Cache extends PHPTAL_Php_Attribute
{
    private $cache_filename_var;

    public function before(PHPTAL_Php_CodeWriter $codewriter)
    {
        // number or variable name followed by time unit
        // optional per expression
        if (!preg_match('/^\s*([0-9]+\s*|[a-zA-Z][\/a-zA-Z0-9_]*\s+)([dhms])\s*(?:\;?\s*per\s+([^;]+)|)\s*$/', $this->expression, $matches)) {
            throw new PHPTAL_ParserException("Cache attribute syntax error: ".$this->expression,
                        $this->phpelement->getSourceFile(), $this->phpelement->getSourceLine());
        }

        $cache_len = $matches[1];
        if (!is_numeric($cache_len)) {
            $cache_len = $codewriter->evaluateExpression($cache_len);

            if (is_array($cache_len)) throw new PHPTAL_ParserException("Chained expressions in cache length are not supported",
                                        $this->phpelement->getSourceFile(), $this->phpelement->getSourceLine());
        }
        switch ($matches[2]) {
            case 'd': $cache_len .= '*24'; /* no break */
            case 'h': $cache_len .= '*60'; /* no break */
            case 'm': $cache_len .= '*60'; /* no break */
        }

        $cache_tag = '"'.addslashes( $this->phpelement->getQualifiedName() . ':' . $this->phpelement->getSourceLine()).'"';

        $cache_per_expression = isset($matches[3])?trim($matches[3]):null;
        if ($cache_per_expression == 'url') {
            $cache_tag .= '.$_SERVER["REQUEST_URI"]';
        } elseif ($cache_per_expression == 'nothing') {
            /* do nothing */
        } elseif ($cache_per_expression) {
             $code = $codewriter->evaluateExpression($cache_per_expression);

             if (is_array($code)) throw new PHPTAL_ParserException("Chained expressions in per-cache directive are not supported",
                                                $this->phpelement->getSourceFile(), $this->phpelement->getSourceLine());

             $cache_tag = '('.$code.')."@".' . $cache_tag;
        }

        $this->cache_filename_var = $codewriter->createTempVariable();
        $codewriter->doSetVar($this->cache_filename_var, $codewriter->str($codewriter->getCacheFilesBaseName()).'.md5('.$cache_tag.')' );

        $cond = '!file_exists('.$this->cache_filename_var.') || time() - '.$cache_len.' >= filemtime('.$this->cache_filename_var.')';

        $codewriter->doIf($cond);
        $codewriter->doEval('ob_start()');
    }

    public function after(PHPTAL_Php_CodeWriter $codewriter)
    {
        $codewriter->doEval('file_put_contents('.$this->cache_filename_var.', ob_get_flush())');
        $codewriter->doElse();
        $codewriter->doEval('readfile('.$this->cache_filename_var.')');
        $codewriter->doEnd('if');

        $codewriter->recycleTempVariable($this->cache_filename_var);
    }
}

