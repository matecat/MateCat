<?php
$test_string="<x id=\"1\">
<x id=\"2\">
<x id=\"3\">
<x id=\"4\">
<x id=\"5\">
<x id=\"6\">
<x id=\"7\">
<x id=\"8\">
<x id=\"9\">
<x id=\"10\">
<g id=\"11\">LISTEN</g>
<g id=\"12\">&nbsp;</g>
<g id=\"13\">&gt;</g>
<g id=\"14\"> </g>
<g id=\"15\">LEARN</g>
<g id=\"16\">&nbsp;</g>
<g id=\"17\">&gt;</g>
<g id=\"18\"> </g>
<g id=\"19\">LEAD</g>
</x>
</x>
</x>
</x>
</x>
</x>
</x>
</x>
</x>
</x>";


$handler = new segmentHandler();   

$parser = xml_parser_create("UTF-8");
xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8");
xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);


xml_set_object($parser,$handler);                                    
xml_set_element_handler($parser, "startElement", "endElement");

xml_set_character_data_handler($parser,"characters");


$a=xml_parse($parser, $test_string);

var_dump ($a);


class segmentHandler {

    var $closed, $indent, $chars;

    function __construct()  {
        $this->closed = false;
        $this->indent = 0;
        $this->chars = "";
    }

    function startElement($parser, $localname, array $attributes) {
        echo " \nstartElement($parser, $localname,";
        print_r ($attributes);
        
        
        $this->flushChars();
        if ($this->closed) {
            print "\n" . str_repeat(' ', $this->indent);
            $this->closed = false;
        }
        $this->indent+=1 + strlen($localname);
        print $localname . "[";
        $first = true;
        foreach ($attributes as $attrName => $attrValue) {
            if (!$first)
                print "\n" . str_repeat(' ', $this->indent);
            print "@" . $attrName . "[" . $attrValue . "]";
            $first = false;
            $this->closed = true;
        }
    }

    function endElement($parser, $localname) {
        
        $this->flushChars();
        print "]";
        $this->closed = true;
        $this->indent-=1 + strlen($localname);
    }

    function characters($parser, $text) {
        
        if (strlen(trim($text)) > 0) {
            if ($this->closed) {
                print "\n" . str_repeat(' ', $this->indent);
                $this->closed = false;
            }
            $this->chars = $this->chars . trim($text);
        }
    }

    function flushChars() {
        
        if (strlen($this->chars) > 0) {
            if ($this->closed) {
                print "\n" . str_repeat(' ', $this->indent);
                $this->closed = false;
            }
            print preg_replace("/ *\n? +/", " ", $this->chars);
            $this->closed = true;
            $this->chars = "";
        }
    }

}

?>
