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
 * You can implement this interface to load templates from various sources (see SourceResolver)
 *
 * @package PHPTAL
 */
interface PHPTAL_Source
{
    /**
     * unique path identifying the template source.
     * must not be empty. must be as unique as possible.
     *
     * it doesn't have to be path on disk.
     *
     * @return string
     */
    public function getRealPath();

    /**
     * template source last modified time (unix timestamp)
     * Return 0 if unknown.
     *
     * If you return 0:
     *  • PHPTAL won't know when to reparse the template,
     *    unless you change realPath whenever template changes.
     *  • clearing of cache will be marginally slower.
     *
     * @return long
     */
    public function getLastModifiedTime();

    /**
     * the template source
     *
     * @return string
     */
    public function getData();
}
