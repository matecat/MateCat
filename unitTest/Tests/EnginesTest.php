<?php
/**
 * Created by JetBrains PhpStorm.
 * User: domenico
 * Date: 09/10/13
 * Time: 15.31
 * 
 */
include_once("AbstractTest.php");
include_once INIT::$MODEL_ROOT . '/queries.php';
include_once INIT::$UTILS_ROOT . '/engines/engine.class.php';
include_once INIT::$UTILS_ROOT . '/engines/tms.class.php';
include_once INIT::$UTILS_ROOT . '/engines/mt.class.php';

//Mock Objects for Mute the curl
class MUTE_TMS extends TMS {

    public $fakeUrl = 'http://api.mymemory.translated.net/get?q=The+law+specifically+exempts+small+firms+that+have+fewer+than+50+full-time+employees.&langpair=en-US%7Cit-IT&de=demo%40matecat.com&mt=1&numres=3';
    public $fakeRes = '{"responseData":{"translatedText":"La legge esenta espressamente le piccole imprese con meno di 50 dipendenti a tempo pieno."},"responseDetails":"","responseStatus":200,"matches":[{"id":"0","segment":"The law specifically exempts small firms that have fewer than 50 full-time employees.","translation":"La legge esenta espressamente le piccole imprese con meno di 50 dipendenti a tempo pieno.","quality":"70","reference":"Machine Translation provided by Google, Microsoft, Worldlingo or MyMemory customized engine.","usage-count":1,"subject":"All","created-by":"MT!","last-updated-by":"MT!","create-date":"2013-10-14","last-update-date":"2013-10-14","match":0.85},{"id":"440094133","segment":"full-time employee","translation":"dipendente a tempo pieno","quality":"74","reference":"","usage-count":1,"subject":"General","created-by":"","last-updated-by":"","create-date":"2013-10-10 18:59:39","last-update-date":"2013-10-10 18:59:39","match":0.15},{"id":"440008758","segment":"5 full-time fundraisers.","translation":"5 dialogatori dedicati.","quality":"74","reference":"","usage-count":2,"subject":"All","created-by":"anonymous","last-updated-by":"anonymous","create-date":"2013-09-12 22:12:55","last-update-date":"2013-09-12 22:12:55","match":0.15}]}';

    protected function curl($url,$postfields=false) {
        return $this->fakeRes;
    }
}
//Mock Objects for Mute the curl
class MUTE_MT extends MT {

    public $fakeUrl = 'http://hlt-services2.fbk.eu:8888/translate?q=Consistent+with+the+coordinated+approach+the+Departments+of+Treasury%2C+Labor%2C+and+Health+and+Human+Services+are+taking+in+developing+the+regulations+and+other+guidance+under+the+Affordable+Care+Act%2C+the+notice+also+solicits+input+on+how+the+three+Departments+should+interpret+and+apply+the+Act%E2%80%99s+provisions+limiting+the+ability+of+plans+and+issuers+to+impose+a+waiting+period+for+health+coverage+of+longer+than+90+days+starting+in+2014.&source=en&target=it&key=';
    public $fakeRes = '{"data": {"translations": [{"segmentID": "0000", "translatedText": "congruente con l\'approccio coordinato il distretti di Tesoreria, Labor, e Health e Human Services sta svolgendo in sviluppo delle normative e altre istruzioni sotto il economiche Care Act, la notifica anche sollecita l\'immissione su come le tre Reparti deve interpretare ed applicare i privilegi Agisci\' s disposizioni limitando la possibilit\u00e0 di piani ed emittenti di imporre un periodo di attesa dello stato di copertura per pi\u00f9 di 90 giorni a partire dalla 2014.", "sentence_confidence": "50.35047642150528", "systemName": "SYSTEM_baseline", "wordAlignment": [[[0], [0]], [[1], [1]], [[2], [2]], [[4], [3]], [[3], [4]], [[5], [5]], [[6], [6]], [[7], [7]], [[8], [8]], [[9], [9]], [[10], [10]], [[11], [11]], [[12], [12]], [[13], [13]], [[14], [14]], [[15], [15]], [[16], [16]], [[17], [17]], [[18], [18]], [[19], [19]], [[20], [20]], [[21], [21]], [[22], [22]], [[23], [23]], [[24], [24]], [[25], [25]], [[26], [26]], [[27], [27]], [[28], [28]], [[29], [29]], [[30], [30]], [[31], [31]], [[32], [32]], [[33], [33]], [[34], [34]], [[35], [35]], [[35], [36]], [[36], [37]], [[37], [38]], [[38], [39]], [[39], [40]], [[40], [41]], [[41], [42]], [[42], [43]], [[43], [44]], [[44], [45]], [[45], [46]], [[46], [47]], [[47], [48]], [[47], [49]], [[48], [50]], [[49], [51]], [[50], [52]], [[51], [53]], [[52], [54]], [[53], [55]], [[54], [56]], [[55], [57]], [[56], [58]], [[57], [59]], [[58], [60]], [[59], [61]], [[60], [62]], [[62], [63]], [[61], [64]], [[61], [65]], [[64], [66]], [[64], [67]], [[66], [68]], [[65], [69]], [[63], [70]], [[67], [71]], [[68], [72]], [[69], [73]], [[70], [74]], [[71], [75]], [[71], [76]], [[72], [77]], [[73], [78]], [[74], [79]]], "phraseAlignment": [[[0], [0]], [[1], [1]], [[2], [2]], [[4], [3]], [[3], [4]], [[5], [5]], [[6], [6]], [[7], [7]], [[8], [8]], [[9], [9]], [[10], [10]], [[11], [11]], [[12], [12]], [[13], [13]], [[14], [14]], [[15], [15]], [[16], [16]], [[17], [17]], [[18], [18]], [[19], [19]], [[20, 21], [20, 21]], [[22], [22]], [[23], [23]], [[24], [24]], [[25], [25]], [[26], [26]], [[27], [27]], [[28], [28]], [[29], [29]], [[30], [30]], [[31], [31]], [[32], [32]], [[33], [33]], [[34], [34]], [[35, 36], [35, 36, 37]], [[37, 38, 39], [38, 39, 40]], [[40], [41]], [[41], [42]], [[42], [43]], [[43], [44]], [[44, 45], [45, 46]], [[46, 47], [47, 48, 49]], [[48], [50]], [[49], [51]], [[50], [52]], [[51], [53]], [[52, 53], [54, 55]], [[54], [56]], [[55], [57]], [[56], [58]], [[57], [59]], [[58, 59], [60, 61]], [[60], [62]], [[61, 62], [63, 64, 65]], [[64], [66, 67]], [[66], [68]], [[65], [69]], [[63], [70]], [[67, 68], [71, 72]], [[69], [73]], [[70], [74]], [[71, 72], [75, 76, 77]], [[73], [78]], [[74], [79]]]}]}}';

    protected function curl($url,$postfields=false) {
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
        $cmd = "mysql -u root < " . dirname( __FILE__ ) . "/unitTest_matecat_local.sql 2>&1";
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

        $config = TMS::getConfigStruct();
        $config[ 'get_mt' ]  = true;
        $config[ 'mt_only' ] = true;
        $config[ 'segment' ]       = "This provision takes effect in 2014.";
        $config[ 'source_lang' ]   = "en-US";
        $config[ 'target_lang' ]   = "it-IT";
        $config[ 'email' ]         = "demo@matecat.com";
        $config[ 'id_user' ]       = null;
        $config[ 'num_result' ]    = 3;
        $config[ 'isConcordance' ] = true;

        $tms = new MUTE_TMS( 1 ); //MyMemory
        $tms->get( $config );

        $class = new \ReflectionObject($tms);
        $prop = $class->getProperty('url');
        $prop->setAccessible(true);

        $this->assertEquals( $expected_String, $prop->getValue($tms) );

    }

    public function testRightUrlForMT(){

        $expected_url = "/translate?q=This+provision+takes+effect+in+2014.&source=en&target=it&key=demo%40matecat.com";

        $domains = array(
            2 => 'http://hlt-services2.fbk.eu:8888',
            3 => 'http://193.52.29.52:8001',
            4 => 'http://hlt-services2.fbk.eu:8988',
            5 => 'http://193.52.29.52:8002',
            6 => 'http://hlt-services2.fbk.eu:8788',
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

    }

    public function test_TMS_GET_MATCHES_Struct(){

        $mt = new MUTE_MT( 2 );

        $urls = parse_url( $mt->fakeUrl );
        parse_str( $urls['query'] );

        /** @var $q string  */
        $text = $q;

        $source = "en-US";
        $target = "it-IT";
        $key  = "";

        $mt_result = $mt->get( $text, $source, $target, $key );

        $mt_match = $mt_result->translatedText;
        $penalty  = $mt->getPenalty();
        $mt_score = 100 - $penalty;
        $mt_score .= "%";

        $mt_match_res = new TMS_GET_MATCHES( $text, $mt_match, $mt_score, "MT-" . $mt->getName(), date("Y-m-d") );

        $mt_res = $mt_match_res->get_as_array();
        $mt_res['sentence_confidence'] = $mt_result->sentence_confidence; //can be null

        $this->assertArrayHasKey( 'id', $mt_res );
        $this->assertArrayHasKey( 'raw_segment', $mt_res );
        $this->assertArrayHasKey( 'segment', $mt_res );
        $this->assertArrayHasKey( 'translation', $mt_res );
        $this->assertArrayHasKey( 'raw_translation', $mt_res );
        $this->assertArrayHasKey( 'quality', $mt_res );
        $this->assertArrayHasKey( 'reference', $mt_res );
        $this->assertArrayHasKey( 'usage_count', $mt_res );
        $this->assertArrayHasKey( 'subject', $mt_res );
        $this->assertArrayHasKey( 'created_by', $mt_res );
        $this->assertArrayHasKey( 'last_updated_by', $mt_res );
        $this->assertArrayHasKey( 'create_date', $mt_res );
        $this->assertArrayHasKey( 'last_update_date', $mt_res );
        $this->assertArrayHasKey( 'match', $mt_res );
        $this->assertArrayHasKey( 'sentence_confidence', $mt_res );
        $this->assertEquals( '50.35047642150528',  $mt_res['sentence_confidence'] );
        $this->assertEquals( 'MT-FBK Legal (EN->IT) - Ad.', $mt_res['created_by'] );
        $this->assertEquals(
            "congruente con l'approccio coordinato il distretti di Tesoreria, Labor, e Health e Human Services sta svolgendo in sviluppo delle normative e altre istruzioni sotto il economiche Care Act, la notifica anche sollecita l'immissione su come le tre Reparti deve interpretare ed applicare i privilegi Agisci' s disposizioni limitando la possibilità di piani ed emittenti di imporre un periodo di attesa dello stato di copertura per più di 90 giorni a partire dalla 2014.",
            $mt_res['raw_translation']
        );
        $this->assertEquals( $text, $mt_res['segment'] );
        $this->assertEquals( $mt_score, '86%' );

    }

    public function testMTConnection(){
//
//        $text = "CAST(<value tocast>, <expected class>)";
//        $source = "en-US";
//        $target = "it-IT";
//        $key  = "demo@matecat.com";
//
//        var_dump( getEngineData(15) );


//        $mt = new MT( 15 );
//
//        $data = getEngineData($id);

//
//        $result = $mt->get( $text, $source, $target, $key );
//        $class = new \ReflectionObject($mt);
//        $prop = $class->getProperty('url');
//        $prop->setAccessible(true);
//
//        Log::doLog( $prop->getValue($mt) );
//        Log::doLog($result);

    }

}

