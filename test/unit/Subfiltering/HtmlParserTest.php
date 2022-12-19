<?php
/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 15/01/20
 * Time: 15:18
 *
 */

namespace unit\Subfiltering;


use AbstractTest;
use Matecat\SubFiltering\Commons\Pipeline;
use Matecat\SubFiltering\Filters\HtmlPlainTextDecoder;
use Matecat\SubFiltering\Filters\HtmlToPh;

class HtmlParserTest extends AbstractTest {

    /**
     * @throws \Exception
     */
    public function test1() {

        //this segment comes from the previous layers ( and it is valid despite the double encoding in the ampersands )
        //we must ignore tags and encode the text to remove the double encoding
        //WARNING the href attribute must NOT decoded
        $segment = "<p> Airbnb &amp;amp; Co. &amp;lt; <strong>Use professional tools</strong> in your <a href=\"/users/settings?test=123&amp;amp;ciccio=1\" target=\"_blank\">";
        $expected = "<p> Airbnb &amp; Co. &lt; <strong>Use professional tools</strong> in your <a href=\"/users/settings?test=123&amp;amp;ciccio=1\" target=\"_blank\">";

        $decoder = new HtmlPlainTextDecoder();
        $str     = $decoder->transform( $segment );

        $this->assertEquals( $expected, $str );

    }

    public function test2() {
        $segment  = "Airbnb &amp;amp; Co. &amp;lt;<p>";
        $expected = "Airbnb &amp; Co. &lt;<p>";
        $decoder = new HtmlPlainTextDecoder();
        $str     = $decoder->transform( $segment );

        $this->assertEquals( $expected, $str );
    }

    public function test3() {
        $segment  = "<p>Airbnb &amp;amp; Co. &amp;lt;";
        $expected = "<p>Airbnb &amp; Co. &lt;";
        $decoder = new HtmlPlainTextDecoder();
        $str     = $decoder->transform( $segment );

        $this->assertEquals( $expected, $str );
    }

    /**
     * @throws \Exception
     */
    public function test4() {

        //this html segment comes from the previous layers
        //we must extract and lock html inside ph tags AS IS
        //WARNING the href attribute MUST NOT BE encoded because we want only extract HTML
        //WARNING the text node inside HTML must remain untouched
        $segment = "<p> Airbnb &amp;amp; Co. &amp;lt; <strong>Use professional tools</strong> in your <a href=\"/users/settings?test=123&amp;amp;ciccio=1\" target=\"_blank\">";
        $expected = "<ph id=\"mtc_1\" equiv-text=\"base64:Jmx0O3AmZ3Q7\"/> Airbnb &amp;amp; Co. &amp;lt; <ph id=\"mtc_2\" equiv-text=\"base64:Jmx0O3N0cm9uZyZndDs=\"/>Use professional tools<ph id=\"mtc_3\" equiv-text=\"base64:Jmx0Oy9zdHJvbmcmZ3Q7\"/> in your <ph id=\"mtc_4\" equiv-text=\"base64:Jmx0O2EgaHJlZj0iL3VzZXJzL3NldHRpbmdzP3Rlc3Q9MTIzJmFtcDthbXA7Y2ljY2lvPTEiIHRhcmdldD0iX2JsYW5rIiZndDs=\"/>";

        $pipeline = new Pipeline();
        $pipeline->addLast( new HtmlToPh() );
        $str = $pipeline->transform( $segment );

        $this->assertEquals( $expected, $str );

    }

}