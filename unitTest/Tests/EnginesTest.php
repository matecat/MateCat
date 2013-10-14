<?php
/**
 * Created by JetBrains PhpStorm.
 * User: domenico
 * Date: 09/10/13
 * Time: 15.31
 * 
 */
include_once("AbstractTest.php");
include_once INIT::$MODEL_ROOT . '/Database.class.php';
include_once INIT::$MODEL_ROOT . '/queries.php';
include_once INIT::$UTILS_ROOT . '/engines/engine.class.php';
include_once INIT::$UTILS_ROOT . '/engines/tms.class.php';
include_once INIT::$UTILS_ROOT . '/engines/mt.class.php';

//Mock Objects for Mute the curl
class MUTE_TMS extends TMS {
    public $fakeRes = '{"responseData":{"translatedText":"Totale"},"responseDetails":"","responseStatus":200,"matches":[{"id":"2851897","segment":"Total","translation":"Totale","quality":"0","reference":"","usage-count":43,"subject":"Marketing","created-by":"","last-updated-by":"sistemi_informativi","create-date":"2007-07-24 16:57:41","last-update-date":"2007-07-24 16:57:41","match":1},{"id":"390646","segment":"Total","translation":"Totali","quality":"0","reference":"","usage-count":1,"subject":"Chemical","created-by":"","last-updated-by":"autel","create-date":"2005-07-06 16:18:14","last-update-date":"2005-07-06 16:18:14","match":0.99},{"id":"912583","segment":"Total:","translation":"Formula:","quality":"0","reference":"","usage-count":1,"subject":"Computer_Science","created-by":"","last-updated-by":"demo","create-date":"2006-12-15 15:26:01","last-update-date":"2006-12-15 15:26:01","match":0.97}]}';
    protected function curl($url) {
        return $this->fakeRes;
    }
}
//Mock Objects for Mute the curl
class MUTE_MT extends MT {
    public $fakeRes = '{"data": {"translations": [{"segmentID": "0000", "translatedText": "Per raccolte commenti e suggerimenti adesso, Treasury e ufficio imposte sono fornendo tutte le parti interessate opportunit\u00e0 per l\'input prima proposta normative sono emessi in un secondo momento.", "sentence_confidence": "76.12714937913083", "systemName": "SYSTEM_baseline", "wordAlignment": [[[0], [0]], [[1], [1]], [[2], [2]], [[3], [3]], [[4], [4]], [[5], [5]], [[6], [6]], [[7], [7]], [[8], [8]], [[9], [9]], [[9], [10]], [[10], [11]], [[11], [12]], [[12], [13]], [[15], [14]], [[14], [15]], [[13], [16]], [[16], [17]], [[17], [18]], [[18], [19]], [[18], [20]], [[19], [21]], [[20], [22]], [[21], [23]], [[22], [24]], [[23], [25]], [[26], [26]], [[25], [27]], [[26], [28]], [[26], [29]], [[28], [30]]], "phraseAlignment": [[[0], [0]], [[1], [1]], [[2], [2]], [[3], [3]], [[4], [4]], [[5], [5]], [[6], [6]], [[7], [7]], [[8], [8]], [[9], [9, 10]], [[10], [11]], [[11], [12]], [[12], [13]], [[15], [14]], [[13, 14], [15, 16]], [[16], [17]], [[17, 18], [18, 19, 20]], [[19], [21]], [[20], [22]], [[21, 22], [23, 24]], [[23], [25]], [[24, 25, 26, 27], [26, 27, 28, 29]], [[28], [30]]]}]}}';
    protected function curl($url) {
        return $this->fakeRes;
    }
}

class Tests_EnginesTest extends Tests_AbstractTest {

    public $DB_SERVER   = "localhost"; //database server
    public $DB_DATABASE = "unittest_matecat_local"; //database name
    public $DB_USER     = "unt_matecat_user"; //database login
    public $DB_PASS     = "unt_matecat_user"; //databasepassword

    public $db;

    public static function setUpBeforeClass(){
        self::_resetDB();
    }

    public function setUp(){
        parent::setUp();
        $db = Database::obtain ( $this->DB_SERVER, $this->DB_USER, $this->DB_PASS, $this->DB_DATABASE );
        $db->connect ();
    }

    protected static function _resetDB(){
        $cmd = "mysql -u root -padmin < " . dirname( __FILE__ ) . "/unitTest_matecat_local.sql 2>&1";
        $res = shell_exec( $cmd );
        if( !is_null($res) ){
            $msg = 'Shell Exec Command Failed: ' . $cmd;
            throw new RuntimeException( $msg );
        }
        echo "." . str_pad( "Database Reset Done" , 40, " ", STR_PAD_LEFT ) . "\n";
    }

    public function testInstanceMTasTMS(){
        $this->setExpectedException('Exception');
        new MT( 1 );
    }

    public function testInstanceTMSasMT(){
        $this->setExpectedException('Exception');
        new TMS( 2 );
    }

    public function testInstanceMT_EmptyParam(){
        $this->setExpectedException('Exception');
        new MT( null );
    }

    public function testInstanceTMS_EmptyParam(){
        $this->setExpectedException('Exception');
        new TMS( null );
    }

    public function testInstanceTMS_NONE(){
        $this->setExpectedException('Exception');
        new TMS( 0 );
    }

    public function testInstanceMT_NONE(){
        $this->setExpectedException('Exception');
        new MT( 0 );
    }

    public function testTMS_notExists(){
        $this->setExpectedException('Exception');
        new TMS( 500 );
    }

    public function testMT_notExists(){
        $this->setExpectedException('Exception');
        new MT( 500 );
    }

    public function testRightUrlForTMS(){

        $expected_String = "http://api.mymemory.translated.net/get?q=This+provision+takes+effect+in+2014.&langpair=en-US%7Cit-IT&de=demo%40matecat.com&mt=1&numres=3&conc=true&mtonly=1";

        $text = "This provision takes effect in 2014.";
        $source = "en-US";
        $target = "it-IT";
        $user  = "demo@matecat.com";
        $mt_from_tms = 1;
        $mt_only = true;
        $id_translator = null;
        $is_concordance = true;

        $tms = new MUTE_TMS( 1 ); //MyMemory
        $tms->get( $text, $source, $target, $user, $mt_from_tms, $id_translator, 3, $mt_only, $is_concordance );

        $class = new \ReflectionObject($tms);
        $prop = $class->getProperty('url');
        $prop->setAccessible(true);

        $this->assertEquals( $expected_String, $prop->getValue($tms) );

    }

    public function testRightUrlForMT(){

        $expected_url = "/translate?q=This+provision+takes+effect+in+2014.&source=en&target=it&key=demo%40matecat.com";

        $domains = array(
            2 => 'http://hlt-services2.fbk.eu:8601',
            3 => 'http://193.52.29.52:8001',
            4 => 'http://hlt-services2.fbk.eu:8701',
            5 => 'http://193.52.29.52:8002',
            6 => 'http://hlt-services2.fbk.eu:8482',
            7 => 'http://hlt-services2.fbk.eu:8702',
        );

        $text = "This provision takes effect in 2014.";
        $source = "en-US";
        $target = "it-IT";
        $key  = "demo@matecat.com";

        $mt = new MUTE_MT( 2 );
        $mt->get( $text, $source, $target, $key );
        $class = new \ReflectionObject($mt);
        $prop = $class->getProperty('url');
        $prop->setAccessible(true);
        $this->assertEquals( $domains[2] . $expected_url, $prop->getValue($mt) );

        $mt = new MUTE_MT( 3 );
        $mt->get( $text, $source, $target, $key );
        $class = new \ReflectionObject($mt);
        $prop = $class->getProperty('url');
        $prop->setAccessible(true);
        $this->assertEquals( $domains[3] . $expected_url, $prop->getValue($mt) );

        $mt = new MUTE_MT( 4 );
        $mt->get( $text, $source, $target, $key );
        $class = new \ReflectionObject($mt);
        $prop = $class->getProperty('url');
        $prop->setAccessible(true);
        $this->assertEquals( $domains[4] . $expected_url, $prop->getValue($mt) );

        $mt = new MUTE_MT( 5 );
        $mt->get( $text, $source, $target, $key );
        $class = new \ReflectionObject($mt);
        $prop = $class->getProperty('url');
        $prop->setAccessible(true);
        $this->assertEquals( $domains[5] . $expected_url, $prop->getValue($mt) );

        $mt = new MUTE_MT( 6 );
        $mt->get( $text, $source, $target, $key );
        $class = new \ReflectionObject($mt);
        $prop = $class->getProperty('url');
        $prop->setAccessible(true);
        $this->assertEquals( $domains[6] . $expected_url, $prop->getValue($mt) );

        $mt = new MUTE_MT( 7 );
        $mt->get( $text, $source, $target, $key );
        $class = new \ReflectionObject($mt);
        $prop = $class->getProperty('url');
        $prop->setAccessible(true);
        $this->assertEquals( $domains[7] . $expected_url, $prop->getValue($mt) );

    }

}

