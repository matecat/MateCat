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
 * @author   Iván Montes <drslump@pollinimini.net>
 * @license  http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @version  SVN: $Id$
 * @link     http://phptal.org/
 */

/**
 * Stores tal:repeat information during template execution.
 *
 * An instance of this class is created and stored into PHPTAL context on each
 * tal:repeat usage.
 *
 * repeat/item/index
 * repeat/item/number
 * ...
 * are provided by this instance.
 *
 * 'repeat' is an stdClass instance created to handle RepeatControllers,
 * 'item' is an instance of this class.
 *
 * @package PHPTAL
 * @subpackage Php
 * @author Laurent Bedubourg <lbedubourg@motion-twin.com>
 */
class PHPTAL_RepeatController implements Iterator
{
    public $key;
    private $current;
    private $valid;
    private $validOnNext;

    private $uses_groups = false;

    protected $iterator;
    public $index;
    public $end;

    /**
     * computed lazily
     */
    private $length = null;

    /**
     * Construct a new RepeatController.
     *
     * @param $source array, string, iterator, iterable.
     */
    public function __construct($source)
    {
        if ( is_string($source) ) {
            $this->iterator = new ArrayIterator( str_split($source) );  // FIXME: invalid for UTF-8 encoding, use preg_match_all('/./u') trick
        } elseif ( is_array($source) ) {
            $this->iterator = new ArrayIterator($source);
        } elseif ($source instanceof IteratorAggregate) {
            $this->iterator = $source->getIterator();
        } elseif ($source instanceof DOMNodeList) {
            $array = array();
            foreach ($source as $k=>$v) {
                $array[$k] = $v;
            }
            $this->iterator = new ArrayIterator($array);
        } elseif ($source instanceof Iterator) {
            $this->iterator = $source;
        } elseif ($source instanceof Traversable) {
            $this->iterator = new IteratorIterator($source);
        } elseif ($source instanceof stdClass) {
            $this->iterator = new ArrayIterator( (array) $source );
        } else {
            $this->iterator = new ArrayIterator( array() );
        }
    }

    /**
     * Returns the current element value in the iteration
     *
     * @return Mixed    The current element value
     */
    public function current()
    {
        return $this->current;
    }

    /**
     * Returns the current element key in the iteration
     *
     * @return String/Int   The current element key
     */
    public function key()
    {
        return $this->key;
    }

    /**
     * Tells if the iteration is over
     *
     * @return bool     True if the iteration is not finished yet
     */
    public function valid()
    {
        $valid = $this->valid || $this->validOnNext;
        $this->validOnNext = $this->valid;

        return $valid;
    }

    public function length()
    {
        if ($this->length === null) {
            if ($this->iterator instanceof Countable) {
                return $this->length = count($this->iterator);
            } elseif ( is_object($this->iterator) ) {
                // for backwards compatibility with existing PHPTAL templates
                if ( method_exists($this->iterator, 'size') ) {
                    return $this->length = $this->iterator->size();
                } elseif ( method_exists($this->iterator, 'length') ) {
                    return $this->length = $this->iterator->length();
                }
            }
            $this->length = '_PHPTAL_LENGTH_UNKNOWN_';
        }

        if ($this->length === '_PHPTAL_LENGTH_UNKNOWN_') // return length if end is discovered
        {
            return $this->end ? $this->index + 1 : null;
        }
        return $this->length;
    }

    /**
     * Restarts the iteration process going back to the first element
     *
     */
    public function rewind()
    {
        $this->index = 0;
        $this->length = null;
        $this->end = false;

        $this->iterator->rewind();

        // Prefetch the next element
        if ($this->iterator->valid()) {
            $this->validOnNext = true;
            $this->prefetch();
        } else {
            $this->validOnNext = false;
        }

        if ($this->uses_groups) {
            // Notify the grouping helper of the change
            $this->groups->reset();
        }
    }

    /**
     * Fetches the next element in the iteration and advances the pointer
     *
     */
    public function next()
    {
        $this->index++;

        // Prefetch the next element
        if ($this->validOnNext) $this->prefetch();

        if ($this->uses_groups) {
            // Notify the grouping helper of the change
            $this->groups->reset();
        }
    }

    /**
     * Ensures that $this->groups works.
     *
     * Groups are rarely-used feature, which is why they're lazily loaded.
     */
    private function initializeGroups()
    {
        if (!$this->uses_groups) {
            $this->groups = new PHPTAL_RepeatControllerGroups();
            $this->uses_groups = true;
        }
    }

    /**
     * Gets an object property
     *
     * @return $var  Mixed  The variable value
     */
    public function __get($var)
    {
        switch ($var) {
            case 'number':
                return $this->index + 1;
            case 'start':
                return $this->index === 0;
            case 'even':
                return ($this->index % 2) === 0;
            case 'odd':
                return ($this->index % 2) === 1;
            case 'length':
                return $this->length();
            case 'letter':
                return strtolower( $this->int2letter($this->index+1) );
            case 'Letter':
                return strtoupper( $this->int2letter($this->index+1) );
            case 'roman':
                return strtolower( $this->int2roman($this->index+1) );
            case 'Roman':
                return strtoupper( $this->int2roman($this->index+1) );

            case 'groups':
                $this->initializeGroups();
                return $this->groups;

            case 'first':
                $this->initializeGroups();
                // Compare the current one with the previous in the dictionary
                $res = $this->groups->first($this->current);
                return is_bool($res) ? $res : $this->groups;

            case 'last':
                $this->initializeGroups();
                // Compare the next one with the dictionary
                $res = $this->groups->last( $this->iterator->current() );
                return is_bool($res) ? $res : $this->groups;

            default:
                throw new PHPTAL_VariableNotFoundException("Unable to find part '$var' in repeat variable");
        }
    }

    /**
     * Fetches the next element from the source data store and
     * updates the end flag if needed.
     *
     * @access protected
     */
    protected function prefetch()
    {
        $this->valid = true;
        $this->current = $this->iterator->current();
        $this->key = $this->iterator->key();

        $this->iterator->next();
        if ( !$this->iterator->valid() ) {
            $this->valid = false;
            $this->end = true;
        }
    }

    /**
     * Converts an integer number (1 based) to a sequence of letters
     *
     * @param int $int  The number to convert
     *
     * @return String   The letters equivalent as a, b, c-z ... aa, ab, ac-zz ...
     * @access protected
     */
    protected function int2letter($int)
    {
        $lookup = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $size = strlen($lookup);

        $letters = '';
        while ($int > 0) {
            $int--;
            $letters = $lookup[$int % $size] . $letters;
            $int = floor($int / $size);
        }
        return $letters;
    }

    /**
     * Converts an integer number (1 based) to a roman numeral
     *
     * @param int $int  The number to convert
     *
     * @return String   The roman numeral
     * @access protected
     */
    protected function int2roman($int)
    {
        $lookup = array(
            '1000'  => 'M',
            '900'   => 'CM',
            '500'   => 'D',
            '400'   => 'CD',
            '100'   => 'C',
            '90'    => 'XC',
            '50'    => 'L',
            '40'    => 'XL',
            '10'    => 'X',
            '9'     => 'IX',
            '5'     => 'V',
            '4'     => 'IV',
            '1'     => 'I',
        );

        $roman = '';
        foreach ($lookup as $max => $letters) {
            while ($int >= $max) {
                $roman .= $letters;
                $int -= $max;
            }
        }

        return $roman;
    }
}

