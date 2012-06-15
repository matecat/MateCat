<?php
/**
 * PHPTAL templating engine
 *
 * PHP Version 5
 *
 * @category HTML
 * @package  PHPTAL
 * @author   Kornel Lesiński <kornel@aardvarkmedia.co.uk>
 * @license  http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @version  SVN: $Id: $
 * @link     http://phptal.org/
 */

/**
 * Base class for prefilters.
 *
 * You should extend this class and override methods you're interested in.
 *
 * Order of calls is undefined and may change.
 *
 * @package PHPTAL
 */
abstract class PHPTAL_PreFilter implements PHPTAL_Filter
{
    /**
     * @see getPHPTAL()
     */
    private $phptal;


    /**
     * Receives DOMElement (of PHP5 DOM API) of parsed file (documentElement), or element
     * that has phptal:filter attribute. Should edit DOM in place.
     * Prefilters are called only once before template is compiled, so they can be slow.
     *
     * Default implementation does nothing. Override it.
     *
     * @param DOMElement $node PHP5 DOM node to modify in place
     *
     * @return void
     */
    public function filterElement(DOMElement $node)
    {
    }

    /**
     * Receives root PHPTAL DOM node of parsed file and should edit it in place.
     * Prefilters are called only once before template is compiled, so they can be slow.
     *
     * Default implementation does nothing. Override it.
     *
     * @see PHPTAL_Dom_Element class for methods and fields available.
     *
     * @param PHPTAL_Dom_Element $root PHPTAL DOM node to modify in place
     *
     * @return void
     */
    public function filterDOM(PHPTAL_Dom_Element $root)
    {
    }

    /**
     * Receives DOM node that had phptal:filter attribute calling this filter.
     * Should modify node in place.
     * Prefilters are called only once before template is compiled, so they can be slow.
     *
     * Default implementation calls filterDOM(). Override it.
     *
     * @param PHPTAL_Dom_Element $node PHPTAL DOM node to modify in place
     *
     * @return void
     */
    public function filterDOMFragment(PHPTAL_Dom_Element $node)
    {
        $this->filterDOM($node);
    }

    /**
     * Receives template source code and is expected to return new source.
     * Prefilters are called only once before template is compiled, so they can be slow.
     *
     * Default implementation does nothing. Override it.
     *
     * @param string $src markup to filter
     *
     * @return string
     */
    public function filter($src)
    {
        return $src;
    }

    /**
     * Returns (any) string that uniquely identifies this filter and its settings,
     * which is used to (in)validate template cache.
     *
     * Unlike other filter methods, this one is called on every execution.
     *
     * Override this method if result of the filter depends on its configuration.
     *
     * @return string
     */
    public function getCacheId()
    {
        return get_class($this);
    }

    /**
     * Returns PHPTAL class instance that is currently using this prefilter.
     * May return NULL if PHPTAL didn't start filtering yet.
     *
     * @return PHPTAL or NULL
     */
    final protected function getPHPTAL()
    {
        return $this->phptal;
    }

    /**
     * Set which instance of PHPTAL is using this filter.
     * Must be done before calling any filter* methods.
     *
     * @param PHPTAL $phptal instance
     */
    final function setPHPTAL(PHPTAL $phptal)
    {
        $this->phptal = $phptal;
    }
}


