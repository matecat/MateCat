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
 * Fake template source that makes PHPTAL->setString() work
 *
 * @package PHPTAL
 */
class PHPTAL_StringSource implements PHPTAL_Source
{
    const NO_PATH_PREFIX = '<string ';

    public function __construct($data, $realpath = null)
    {
        $this->_data = $data;
        $this->_realpath = $realpath ? $realpath : self::NO_PATH_PREFIX.md5($data).'>';
    }

    public function getLastModifiedTime()
    {
        if (substr($this->_realpath, 0, 8) !== self::NO_PATH_PREFIX && file_exists($this->_realpath)) {
            return @filemtime($this->_realpath);
        }
        return 0;
    }

    public function getData()
    {
        return $this->_data;
    }

    /**
     * well, this is not always a real path. If it starts with self::NO_PATH_PREFIX, then it's fake.
     */
    public function getRealPath()
    {
        return $this->_realpath;
    }
}

