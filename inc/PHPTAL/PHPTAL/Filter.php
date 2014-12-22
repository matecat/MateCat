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
 * Objects passed to PHPTAL::setPre/PostFilter() must implement this interface
 *
 * @package PHPTAL
 */
interface PHPTAL_Filter
{
    /**
     * In prefilter it gets template source file and is expected to return new source.
     * Prefilters are called only once before template is compiled, so they can be slow.
     *
     * In postfilter template output is passed to this method, and final output goes to the browser.
     * TAL or PHP tags won't be executed. Postfilters should be fast.
     */
    public function filter($str);
}

