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
 * Simple sax like xml parser for PHPTAL
 * ("Dom" in the class name comes from name of the directory, not mode of operation)
 *
 * At the time this parser was created, standard PHP libraries were not suitable
 * (could not retrieve doctypes, xml declaration, problems with comments and CDATA).
 *
 * There are still some problems: XML parsers don't care about exact format of enties
 * or CDATA sections (PHPTAL tries to preserve them),
 * <?php ?> blocks are not allowed in attributes.
 *
 * This parser failed to enforce some XML well-formedness constraints,
 * and there are ill-formed templates "in the wild" because of this.
 *
 * @package PHPTAL
 * @subpackage Dom
 * @see PHPTAL_DOM_DocumentBuilder
 */
class PHPTAL_Dom_SaxXmlParser
{
    private $_file;
    private $_line;
    private $_source;

    // available parser states
    const ST_ROOT = 0;
    const ST_TEXT = 1;
    const ST_LT   = 2;
    const ST_TAG_NAME = 3;
    const ST_TAG_CLOSE = 4;
    const ST_TAG_SINGLE = 5;
    const ST_TAG_ATTRIBUTES = 6;
    const ST_TAG_BETWEEN_ATTRIBUTE = 7;
    const ST_CDATA = 8;
    const ST_COMMENT = 9;
    const ST_DOCTYPE = 10;
    const ST_XMLDEC = 11;
    const ST_PREPROC = 12;
    const ST_ATTR_KEY = 13;
    const ST_ATTR_EQ = 14;
    const ST_ATTR_QUOTE = 15;
    const ST_ATTR_VALUE = 16;

    const BOM_STR = "\xef\xbb\xbf";


    static $state_names = array(
      self::ST_ROOT => 'root node',
      self::ST_TEXT => 'text',
      self::ST_LT   => 'start of tag',
      self::ST_TAG_NAME => 'tag name',
      self::ST_TAG_CLOSE => 'closing tag',
      self::ST_TAG_SINGLE => 'self-closing tag',
      self::ST_TAG_ATTRIBUTES => 'tag',
      self::ST_TAG_BETWEEN_ATTRIBUTE => 'tag attributes',
      self::ST_CDATA => 'CDATA',
      self::ST_COMMENT => 'comment',
      self::ST_DOCTYPE => 'doctype',
      self::ST_XMLDEC => 'XML declaration',
      self::ST_PREPROC => 'preprocessor directive',
      self::ST_ATTR_KEY => 'attribute name',
      self::ST_ATTR_EQ => 'attribute value',
      self::ST_ATTR_QUOTE => 'quoted attribute value',
      self::ST_ATTR_VALUE => 'unquoted attribute value',
    );

    private $input_encoding;
    public function __construct($input_encoding)
    {
        $this->input_encoding = $input_encoding;
        $this->_file = "<string>";
    }

    public function parseFile(PHPTAL_Dom_DocumentBuilder $builder, $src)
    {
        if (!file_exists($src)) {
            throw new PHPTAL_IOException("file $src not found");
        }
        return $this->parseString($builder, file_get_contents($src), $src);
    }

    public function parseString(PHPTAL_Dom_DocumentBuilder $builder, $src, $filename = '<string>')
    {
        try
        {
            $builder->setEncoding($this->input_encoding);
            $this->_file = $filename;

            $this->_line = 1;
            $state = self::ST_ROOT;
            $mark  = 0;
            $len   = strlen($src);

            $quoteStyle = '"';
            $tagname    = "";
            $attribute  = "";
            $attributes = array();

            $customDoctype = false;

            $builder->setSource($this->_file, $this->_line);
            $builder->onDocumentStart();

            $i=0;
            // remove BOM (UTF-8 byte order mark)...
            if (substr($src, 0, 3) === self::BOM_STR) {
                $i=3;
            }
            for (; $i<$len; $i++) {
                $c = $src[$i]; // Change to substr($src, $i, 1); if you want to use mb_string.func_overload

                if ($c === "\n") $builder->setSource($this->_file, ++$this->_line);

                switch ($state) {
                    case self::ST_ROOT:
                        if ($c === '<') {
                            $mark = $i; // mark tag start
                            $state = self::ST_LT;
                        } elseif (!self::isWhiteChar($c)) {
                            $this->raiseError("Characters found before beginning of the document! (wrap document in < tal:block > to avoid this error)");
                        }
                        break;

                    case self::ST_TEXT:
                        if ($c === '<') {
                            if ($mark != $i) {
                                $builder->onElementData($this->sanitizeEscapedText($this->checkEncoding(substr($src, $mark, $i-$mark))));
                            }
                            $mark = $i;
                            $state = self::ST_LT;
                        }
                        break;

                    case self::ST_LT:
                        if ($c === '/') {
                            $mark = $i+1;
                            $state = self::ST_TAG_CLOSE;
                        } elseif ($c === '?' and strtolower(substr($src, $i, 5)) === '?xml ') {
                            $state = self::ST_XMLDEC;
                        } elseif ($c === '?') {
                            $state = self::ST_PREPROC;
                        } elseif ($c === '!' and substr($src, $i, 3) === '!--') {
                            $state = self::ST_COMMENT;
                        } elseif ($c === '!' and substr($src, $i, 8) === '![CDATA[') {
                            $state = self::ST_CDATA;
                            $mark = $i+8; // past opening tag
                        } elseif ($c === '!' and strtoupper(substr($src, $i, 8)) === '!DOCTYPE') {
                            $state = self::ST_DOCTYPE;
                        } elseif (self::isWhiteChar($c)) {
                            $state = self::ST_TEXT;
                        } else {
                            $mark = $i; // mark node name start
                            $attributes = array();
                            $attribute = "";
                            $state = self::ST_TAG_NAME;
                        }
                        break;

                    case self::ST_TAG_NAME:
                        if (self::isWhiteChar($c) || $c === '/' || $c === '>') {
                            $tagname = substr($src, $mark, $i-$mark);
                            if (!$this->isValidQName($tagname)) $this->raiseError("Invalid tag name '$tagname'");

                            if ($c === '/') {
                                $state = self::ST_TAG_SINGLE;
                            } elseif ($c === '>') {
                                $mark = $i+1; // mark text start
                                $state = self::ST_TEXT;
                                $builder->onElementStart($tagname, $attributes);
                            } else /* isWhiteChar */ {
                                $state = self::ST_TAG_ATTRIBUTES;
                            }
                        }
                        break;

                    case self::ST_TAG_CLOSE:
                        if ($c === '>') {
                            $tagname = rtrim(substr($src, $mark, $i-$mark));
                            $builder->onElementClose($tagname);
                            $mark = $i+1; // mark text start
                            $state = self::ST_TEXT;
                        }
                        break;

                    case self::ST_TAG_SINGLE:
                        if ($c !== '>') {
                            $this->raiseError("Expected '/>', but found '/$c' inside tag < $tagname >");
                        }
                        $mark = $i+1;   // mark text start
                        $state = self::ST_TEXT;
                        $builder->onElementStart($tagname, $attributes);
                        $builder->onElementClose($tagname);
                        break;

                    case self::ST_TAG_BETWEEN_ATTRIBUTE:
                    case self::ST_TAG_ATTRIBUTES:
                        if ($c === '>') {
                            $mark = $i+1;   // mark text start
                            $state = self::ST_TEXT;
                            $builder->onElementStart($tagname, $attributes);
                        } elseif ($c === '/') {
                            $state = self::ST_TAG_SINGLE;
                        } elseif (self::isWhiteChar($c)) {
                            $state = self::ST_TAG_ATTRIBUTES;
                        } elseif ($state === self::ST_TAG_ATTRIBUTES && $this->isValidQName($c)) {
                            $mark = $i; // mark attribute key start
                            $state = self::ST_ATTR_KEY;
                        } else $this->raiseError("Unexpected character '$c' between attributes of < $tagname >");
                        break;

                    case self::ST_COMMENT:
                        if ($c === '>' && $i > $mark+4 && substr($src, $i-2, 2) === '--') {

                            if (preg_match('/^-|--|-$/', substr($src, $mark +4, $i-$mark+1 -7))) {
                                $this->raiseError("Ill-formed comment. XML comments are not allowed to contain '--' or start/end with '-': ".substr($src, $mark+4, $i-$mark+1-7));
                            }

                            $builder->onComment($this->checkEncoding(substr($src, $mark+4, $i-$mark+1-7)));
                            $mark = $i+1; // mark text start
                            $state = self::ST_TEXT;
                        }
                        break;

                    case self::ST_CDATA:
                        if ($c === '>' and substr($src, $i-2, 2) === ']]') {
                            $builder->onCDATASection($this->checkEncoding(substr($src, $mark, $i-$mark-2)));
                            $mark = $i+1; // mark text start
                            $state = self::ST_TEXT;
                        }
                        break;

                    case self::ST_XMLDEC:
                        if ($c === '?' && substr($src, $i, 2) === '?>') {
                            $builder->onXmlDecl($this->checkEncoding(substr($src, $mark, $i-$mark+2)));
                            $i++; // skip '>'
                            $mark = $i+1; // mark text start
                            $state = self::ST_TEXT;
                        }
                        break;

                    case self::ST_DOCTYPE:
                        if ($c === '[') {
                            $customDoctype = true;
                        } elseif ($customDoctype && $c === '>' && substr($src, $i-1, 2) === ']>') {
                            $customDoctype = false;
                            $builder->onDocType($this->checkEncoding(substr($src, $mark, $i-$mark+1)));
                            $mark = $i+1; // mark text start
                            $state = self::ST_TEXT;
                        } elseif (!$customDoctype && $c === '>') {
                            $customDoctype = false;
                            $builder->onDocType($this->checkEncoding(substr($src, $mark, $i-$mark+1)));
                            $mark = $i+1; // mark text start
                            $state = self::ST_TEXT;
                        }
                        break;

                    case self::ST_PREPROC:
                        if ($c === '>' and substr($src, $i-1, 1) === '?') {
                            $builder->onProcessingInstruction($this->checkEncoding(substr($src, $mark, $i-$mark+1)));
                            $mark = $i+1; // mark text start
                            $state = self::ST_TEXT;
                        }
                        break;

                    case self::ST_ATTR_KEY:
                        if ($c === '=' || self::isWhiteChar($c)) {
                            $attribute = substr($src, $mark, $i-$mark);
                            if (!$this->isValidQName($attribute)) {
                                $this->raiseError("Invalid attribute name '$attribute' in < $tagname >");
                            }
                            if (isset($attributes[$attribute])) {
                                $this->raiseError("Attribute $attribute in < $tagname > is defined more than once");
                            }

                            if ($c === '=') $state = self::ST_ATTR_VALUE;
                            else /* white char */ $state = self::ST_ATTR_EQ;
                        } elseif ($c === '/' || $c==='>') {
                            $attribute = substr($src, $mark, $i-$mark);
                            if (!$this->isValidQName($attribute)) {
                                $this->raiseError("Invalid attribute name '$attribute'");
                            }
                            $this->raiseError("Attribute $attribute does not have value (found end of tag instead of '=')");
                        }
                        break;

                    case self::ST_ATTR_EQ:
                        if ($c === '=') {
                            $state = self::ST_ATTR_VALUE;
                        } elseif (!self::isWhiteChar($c)) {
                            $this->raiseError("Attribute $attribute in < $tagname > does not have value (found character '$c' instead of '=')");
                        }
                        break;

                    case self::ST_ATTR_VALUE:
                        if (self::isWhiteChar($c)) {
                        } elseif ($c === '"' or $c === '\'') {
                            $quoteStyle = $c;
                            $state = self::ST_ATTR_QUOTE;
                            $mark = $i+1; // mark attribute real value start
                        } else {
                            $this->raiseError("Value of attribute $attribute in < $tagname > is not in quotes (found character '$c' instead of quote)");
                        }
                        break;

                    case self::ST_ATTR_QUOTE:
                        if ($c === $quoteStyle) {
                            $attributes[$attribute] = $this->sanitizeEscapedText($this->checkEncoding(substr($src, $mark, $i-$mark)));

                            // PHPTAL's code generator assumes input is escaped for double-quoted strings. Single-quoted attributes need to be converted.
                            // FIXME: it should be escaped at later stage.
                            $attributes[$attribute] = str_replace('"',"&quot;", $attributes[$attribute]);
                            $state = self::ST_TAG_BETWEEN_ATTRIBUTE;
                        }
                        break;
                }
            }

            if ($state === self::ST_TEXT) // allows text past root node, which is in violation of XML spec
            {
                if ($i > $mark) {
                    $text = substr($src, $mark, $i-$mark);
                    if (!ctype_space($text)) $this->raiseError("Characters found after end of the root element (wrap document in < tal:block > to avoid this error)");
                }
            } else {
                if ($state === self::ST_ROOT) {
                    $msg = "Document does not have any tags";
                } else {
                    $msg = "Finished document in unexpected state: ".self::$state_names[$state]." is not finished";
                }
                $this->raiseError($msg);
            }

            $builder->onDocumentEnd();
        }
        catch(PHPTAL_TemplateException $e)
        {
            $e->hintSrcPosition($this->_file, $this->_line);
            throw $e;
        }
        return $builder;
    }

    private function isValidQName($name)
    {
        $name = $this->checkEncoding($name);
        return preg_match('/^([a-z_\x80-\xff]+[a-z0-9._\x80-\xff-]*:)?[a-z_\x80-\xff]+[a-z0-9._\x80-\xff-]*$/i', $name);
    }

    private function checkEncoding($str)
    {
        if ($str === '') return '';

        if ($this->input_encoding === 'UTF-8') {

            // $match expression below somehow triggers quite deep recurrency and stack overflow in preg
            // to avoid this, check string bit by bit, omitting ASCII fragments.
            if (strlen($str) > 200) {
                $chunks = preg_split('/(?>[\x09\x0A\x0D\x20-\x7F]+)/',$str,null,PREG_SPLIT_NO_EMPTY);
                foreach ($chunks as $chunk) {
                    if (strlen($chunk) < 200) {
                        $this->checkEncoding($chunk);
                    }
                }
                return $str;
            }

            // http://www.w3.org/International/questions/qa-forms-utf-8
            $match = '[\x09\x0A\x0D\x20-\x7F]'        // ASCII
               . '|[\xC2-\xDF][\x80-\xBF]'            // non-overlong 2-byte
               . '|\xE0[\xA0-\xBF][\x80-\xBF]'        // excluding overlongs
               . '|[\xE1-\xEC\xEE\xEE][\x80-\xBF]{2}' // straight 3-byte (exclude FFFE and FFFF)
               . '|\xEF[\x80-\xBE][\x80-\xBF]'        // straight 3-byte
               . '|\xEF\xBF[\x80-\xBD]'               // straight 3-byte
               . '|\xED[\x80-\x9F][\x80-\xBF]'        // excluding surrogates
               . '|\xF0[\x90-\xBF][\x80-\xBF]{2}'     // planes 1-3
               . '|[\xF1-\xF3][\x80-\xBF]{3}'         // planes 4-15
               . '|\xF4[\x80-\x8F][\x80-\xBF]{2}';    // plane 16

            if (!preg_match('/^(?:(?>'.$match.'))+$/s',$str)) {
                $res = preg_split('/((?>'.$match.')+)/s',$str,null,PREG_SPLIT_DELIM_CAPTURE);
                for($i=0; $i < count($res); $i+=2)
                {
                    $res[$i] = self::convertBytesToEntities(array(1=>$res[$i]));
                }
                $this->raiseError("Invalid UTF-8 bytes: ".implode('', $res));
            }
        }
        if ($this->input_encoding === 'ISO-8859-1') {

            // http://www.w3.org/TR/2006/REC-xml11-20060816/#NT-RestrictedChar
            $forbid = '/((?>[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x84\x86-\x9F]+))/s';

            if (preg_match($forbid, $str)) {
                $str = preg_replace_callback($forbid, array('self', 'convertBytesToEntities'), $str);
                $this->raiseError("Invalid ISO-8859-1 characters: ".$str);
            }
        }

        return $str;
    }

    /**
     * preg callback
     * Changes all bytes to hexadecimal XML entities
     *
     * @param array $m first array element is used for input
     *
     * @return string
     */
    private static function convertBytesToEntities(array $m)
    {
        $m = $m[1]; $out = '';
        for($i=0; $i < strlen($m); $i++)
        {
            $out .= '&#X'.strtoupper(dechex(ord($m[$i]))).';';
        }
        return $out;
    }

    /**
     * This is where this parser violates XML and refuses to be an annoying bastard.
     */
    private function sanitizeEscapedText($str)
    {
        $str = str_replace('&apos;', '&#39;', $str); // PHP's html_entity_decode doesn't seem to support that!

        /* <?php ?> blocks can't reliably work in attributes (due to escaping impossible in XML)
           so they have to be converted into special TALES expression
        */
        $types = ini_get('short_open_tag')?'php|=|':'php';
        $str = preg_replace_callback("/<\?($types)(.*?)\?>/", array('self', 'convertPHPBlockToTALES'), $str);

        // corrects all non-entities and neutralizes potentially problematic CDATA end marker
        $str = strtr(preg_replace('/&(?!(?:#x?[a-f0-9]+|[a-z][a-z0-9]*);)/i', '&amp;', $str), array('<'=>'&lt;', ']]>'=>']]&gt;'));

        return $str;
    }

    private static function convertPHPBlockToTALES($m)
    {
        list(, $type, $code) = $m;
        if ($type === '=') $code = 'echo '.$code;
        return '${structure phptal-internal-php-block:'.rawurlencode($code).'}';
    }

    public function getSourceFile()
    {
        return $this->_file;
    }

    public function getLineNumber()
    {
        return $this->_line;
    }

    public static function isWhiteChar($c)
    {
        return strpos(" \t\n\r\0", $c) !== false;
    }

    protected function raiseError($errStr)
    {
        throw new PHPTAL_ParserException($errStr, $this->_file, $this->_line);
    }
}
