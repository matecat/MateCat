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
 * @version  SVN: $Id:$
 * @link     http://phptal.org/
 */

class PHPTAL_Tokenizer
{
    private $regex, $names, $offset, $str;

    private $current_token, $current_value;

    function __construct($str, array $tokens)
    {
        $this->offset = 0;
        $this->str = $str;
        $this->end = strlen($str);

        $this->regex = '/('.str_replace('/', '\/', implode(')|(', $tokens)).')|(.)/Ssi';
        $this->names = array_keys($tokens);
        $this->names[] = 'OTHER';
    }

    function eof()
    {
        return $this->offset >= $this->end;
    }

    function skipSpace()
    {
        while ($this->current_token === 'SPACE') $this->nextToken();
    }

    function nextToken()
    {
        if ($this->offset >= $this->end) {
            $this->current_value = null;
            return $this->current_token = 'EOF';
        }

        //if (!preg_match_all($this->regex, $this->str, $m, PREG_SET_ORDER, $this->offset)) throw new Exception("FAIL {$this->regex} at {$this->offset}");
        if (!preg_match($this->regex, $this->str, $m, null, $this->offset)) throw new Exception("FAIL {$this->regex} didn't match '{$this->str}' at {$this->offset}");

        $this->offset += strlen($m[0]); // in bytes

        $this->current_value = $m[0];
        $this->current_token = $this->names[count($m)-2]; // -1 for usual length/offset confusion, and minus one extra for $m[0]

        return $this->current_token;
    }

    function token()
    {
        return $this->current_token;
    }

    function tokenValue()
    {
        return $this->current_value;
    }
}
