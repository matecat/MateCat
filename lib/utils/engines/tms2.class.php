<?
include_once "engine.class.php";
class TMS_GET_MATCHES {

    public $id;
    public $segment;
    public $translation;
    public $raw_translation;
    public $quality;
    public $reference;
    public $usage_count;
    public $subject;
    public $created_by;
    public $last_updated_by;
    public $create_date;
    public $last_update_date;
    public $match;

    public function __construct() {
        $args = func_get_args();
        if (empty($args)) {
            throw new Exception("No args defined for " . __CLASS__ . " constructor");
        }

        $match = array();
        if (count($args) == 1 and is_array($args[0])) {
            $match = $args[0];
           // print_r ($match);exit;;
            if ($match['last-update-date'] == "0000-00-00 00:00:00") {
                $match['last-update-date'] = "0000-00-00";
            }
            if (!empty($match['last-update-date']) and $match['last-update-date'] != '0000-00-00') {
                $match['last-update-date'] = date("Y-m-d", strtotime($match['last-update-date']));
            }

            if (empty($match['created-by'])) {
                $match['created-by'] = "Anonymous";
            }

            $match['match'] = $match['match'] * 100;
            $match['match'] = $match['match'] . "%";
            
        }
        
        if (count($args) > 1  and is_array($args[0])) {
            throw new Exception("Invalid arg 1 " . __CLASS__ . " constructor");
        }
                
        if (count($args) == 5 and !is_array($args[0])) {
            $match['segment'] = $args[0];
            $match['translation'] = $args[1];
            $match['raw_translation'] = htmlentities($args[1]);
            $match['match'] = $args[2];
            $match['created-by'] = $args[3];
            $match['last-update-date'] = $args[4];
        }


        $this->id = array_key_exists('id', $match) ? $match['id'] : '0';
        $this->create_date = array_key_exists('create-date', $match) ? $match['create-date'] : '0000-00-00';
        $this->segment = array_key_exists('segment', $match) ? $match['segment'] : '';
        $this->translation = array_key_exists('translation', $match) ? $match['translation'] : '';
	$this->raw_translation = array_key_exists('raw_translation', $match) ? $match['raw_translation'] : '';
        $this->quality = array_key_exists('quality', $match) ? $match['quality'] : 0;
        $this->reference = array_key_exists('reference', $match) ? $match['reference'] : '';
        $this->usage_count = array_key_exists('usage-count', $match) ? $match['usage-count'] : 0;
        $this->subject = array_key_exists('subject', $match) ? $match['subject'] : '';
        $this->created_by = array_key_exists('created-by', $match) ? $match['created-by'] : '';
        $this->last_updated_by = array_key_exists('last-updated-by', $match) ? $match['last-updated-by'] : '';
        $this->last_update_date = array_key_exists('last-update-date', $match) ? $match['last-update-date'] : '0000-00-00';
        $this->match = array_key_exists('match', $match) ? $match['match'] : 0;
    }

    public function get_as_array() {
        return ((array) $this);
    }

}

class TMS_RESULT {

    public $responseStatus = "";
    public $responseDetails = "";
    public $responseData = "";
    public $matches = array();

    public function __construct($result) {
        $this->responseData = $result['responseData'];
        $this->responseDetails = isset($result['responseDetails']) ? $result['responseDetails'] : '';
        $this->responseStatus = $result['responseStatus'];

        if (array_key_exists('matches', $result)) {
            $matches = $result['matches'];
            if (is_array($matches) and !empty($matches)) {
                foreach ($matches as $match) {
			$match['raw_translation']=htmlentities($match['translation']);
		    
                    $a = new TMS_GET_MATCHES($match);
                    $this->matches[] = $a;
                }
            }
        }
    }

    public function get_matches_as_array() {
        $a = array();
        foreach ($this->matches as $match) {
            $item = $match->get_as_array();
            $a[] = $item;
        }
        return $a;
    }

    public function get_as_array() {
        return ((array) $this);
    }

}

class TMS extends engine {

    private $result = array();

    public function __construct($id) {
        parent::__construct($id);
        if ($this->type != "TM") {
            throw new Exception("not a TMS engine");
        }
    }

    public function get($segment, $source_lang, $target_lang, $email = "", $mt = 1, $id_user = "") {
        $parameters = array();
        $parameters['q'] = $segment;
        $parameters['langpair'] = "$source_lang|$target_lang";
        $parameters['de'] = $email;
        $parameters['mt'] = $mt;
        if (!empty($id_user)) {
            $parameters['user'] = $id_user;
            $parameters['key'] = $this->calculateMyMemoryKey($id_user);
        }

        $this->doQuery("get", $parameters);
        $this->result = new TMS_RESULT($this->raw_result);
        if ($this->result->responseStatus != "200") {
            return false;
        }
        return $this->result->get_matches_as_array();
    }

    public function set($segment, $translation, $source_lang, $target_lang, $email = "", $id_user = "") {
        $parameters = array();
        $parameters['seg'] = $segment;
        $parameters['tra'] = $translation;
        $parameters['langpair'] = "$source_lang|$target_lang";
        $parameters['de'] = $email;
        if (!empty($id_user)) {
            $parameters['user'] = $id_user;
            $parameters['key'] = $this->calculateMyMemoryKey($id_user);
        }

        $this->doQuery("set", $parameters);
        $this->result = new TMS_RESULT($this->raw_result);
        if ($this->result->responseStatus != "200") {
            return false;
        }
        return true;
    }

    public function delete($segment, $translation, $source_lang, $target_lang, $email = "",$id_user="") {
        $parameters = array();
        $parameters['seg'] = $segment;
        $parameters['tra'] = $translation;
        $parameters['langpair'] = "$source_lang|$target_lang";
        $parameters['de'] = $email;
         if (!empty($id_user)) {
            $parameters['user'] = $id_user;
            $parameters['key'] = $this->calculateMyMemoryKey($id_user);
        }

        $this->doQuery("delete", $parameters);
        $this->result = new TMS_RESULT($this->raw_result);
        if ($this->result->responseStatus != "200") {
            return false;
        }
        return true;
    }
    private function calculateMyMemoryKey($id_translator) {
        $pass = getTranslatorPass($id_translator);
        if (empty($pass)) {
            return null;
        }
        $id_translator = strtolower($id_translator);
        $pass = strtolower($pass);

        $cry1 = md5("$id_translator:$pass");
        $rev_pass = strrev($pass);

        $key = crypt($cry1, "$rev_pass.translated.$id_translator");
        //log::doLog("$id_translator --- $key");
        return $key;
    }
}

/*
$segment = "pannello di controllo";
$translation = "control panel zzzzz";
$source_lang = "it";
$target_lang = "fr";
$email = "antonio@translated.net";


$a = new TMS(1);
print_r($a->get($segment, $source_lang, $target_lang, $email));
print_r($a->getError());
//print_r($a->getRawResults());
*/
