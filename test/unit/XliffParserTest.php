<?php 

include_once INIT::$UTILS_ROOT . "/xliff.parser.1.3.class.php";

class XliffParserTest extends AbstractTest {

    function testParsesWithNoErrors() {
        // read a file with notes inside
        $file = test_file_path('xliff/file-with-notes-nobase64.po.sdlxliff');
        $content = file_get_contents( $file ); 

        $xliff_obj = new Xliff_Parser();
        $xliff     = $xliff_obj->Xliff2Array( $content );
    }

    function testParsesNoteElements() {
        // read a file with notes inside
        $file = test_file_path('xliff/file-with-notes-nobase64.po.sdlxliff');
        $content = file_get_contents( $file ); 

        $xliff_obj = new Xliff_Parser();
        $xliff     = $xliff_obj->Xliff2Array( $content );

        $this->assertEquals( 'This is a comment',
            $xliff['files'][1]['trans-units'][4]['notes'][0]['raw-content']);
        
        $this->assertEquals( 'This is another comment',
            $xliff['files'][1]['trans-units'][6]['notes'][0]['raw-content']);
    }

    function testConvertedFile() {
        $file = test_file_path('xliff/file-with-notes-converted.xliff');
        $content = file_get_contents( $file );

        $xliff_obj = new Xliff_Parser();
        $xliff     = $xliff_obj->Xliff2Array( $content );

        $this->assertEquals(
            "This is a comment\n" .
            "---\n" .
            "This is a comment number two\n" .
            "---\n" .
            "This is a comment number three",

            $xliff['files'][3]['trans-units'][1]['notes'][0]['raw-content']
        );

        $this->assertEquals( 'This is another comment',
            $xliff['files'][3]['trans-units'][3]['notes'][0]['raw-content']);

    }

    function testFileWithMaliciousNote() {
        $file = test_file_path('xliff/file-with-notes-and-malicious-code.xliff');
        $content = file_get_contents( $file );

        $xliff_obj = new Xliff_Parser();
        $xliff     = $xliff_obj->Xliff2Array( $content );

        $this->assertEquals(
           "&lt;script&gt;alert(&#039;This is malicious code&#039;);&lt;/script&gt;",
            $xliff['files'][3]['trans-units'][1]['notes'][0]['raw-content']
        );

    }

    public function testFixNotWellFormedXML(){

        $tests = array(
                '' => '',
                'just text' => 'just text',
                '<gap>Hey</gap>' => '&lt;gap&gt;Hey&lt;/gap&gt;',
                '<mrk>Hey</mrk>' => '<mrk>Hey</mrk>',
                '<g >Hey</g >' => '<g >Hey</g >',
                '<g    >Hey</g   >' => '<g    >Hey</g   >',
                '<g id="99">Hey</g>' => '<g id="99">Hey</g>',
                'Hey<x/>' => 'Hey<x/>',
                'Hey<x />' => 'Hey<x />',
                'Hey<x   />' => 'Hey<x   />',
                'Hey<x id="15"/>' => 'Hey<x id="15"/>',
                'Hey<bx id="1"/>' => 'Hey<bx id="1"/>',
                'Hey<ex id="1"/>' => 'Hey<ex id="1"/>',
                '<bpt id="1">Hey</bpt>' => '<bpt id="1">Hey</bpt>',
                '<ept id="1">Hey</ept>' => '<ept id="1">Hey</ept>',
                '<ph id="1">Hey</ph>' => '<ph id="1">Hey</ph>',
                '<it id="1">Hey</it>' => '<it id="1">Hey</it>',
                '<mrk mid="3" mtype="seg"><g id="2">Hey man! <x id="1"/><b id="dunno">Hey man & hey girl!</b></mrk>' => '<mrk mid="3" mtype="seg"><g id="2">Hey man! <x id="1"/>&lt;b id=&quot;dunno&quot;&gt;Hey man &amp; hey girl!&lt;/b&gt;</mrk>',
        );

        foreach ($tests as $in => $expected) {
            $out = Xliff_Parser::fix_non_well_formed_xml($in);
            $this->assertEquals( $expected, $out );
        }

    }

}
