<?php 

include_once INIT::$UTILS_ROOT . "/xliff.parser.1.3.class.php";

class Xliff_ParserTest extends AbstractTest {

    function setUp() {

    }

    function tearDown() {

    }

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
            $xliff['files'][1]['trans-units'][4]['notes'][0]['raw_content']);
        
        $this->assertEquals( 'This is another comment',
            $xliff['files'][1]['trans-units'][6]['notes'][0]['raw_content']);
    }

}
