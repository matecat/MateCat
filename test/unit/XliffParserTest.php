<?php 

include_once INIT::$UTILS_ROOT . "/xliff.parser.1.3.class.php";

class XliffParserTest extends AbstractTest {

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

}
