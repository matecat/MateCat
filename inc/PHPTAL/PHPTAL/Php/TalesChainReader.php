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
interface PHPTAL_Php_TalesChainReader
{
    public function talesChainNothingKeyword(PHPTAL_Php_TalesChainExecutor $executor);
    public function talesChainDefaultKeyword(PHPTAL_Php_TalesChainExecutor $executor);
    public function talesChainPart(PHPTAL_Php_TalesChainExecutor $executor, $expression, $islast);
}
