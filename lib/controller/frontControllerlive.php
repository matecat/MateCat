<?php


function __autoload($action) {
    //echo "autoload $action\n";
    require_once INIT::$CONTROLLER_ROOT."/$action.php";
}

class controllerDispatcher {

    private static $instance;
    private function __construct() {}

    public static function obtain() {
        if (!self::$instance) {
            self::$instance = new controllerDispatcher();
        }
        return self::$instance;
    }

    public function getController() {//print_r($_REQUEST);exit;
        //Default :  cat
        $action = (isset($_POST['action'])) ? $_POST['action'] : (isset($_GET['action'])?$_GET['action']:'cat');
        //$action='cat';

        $postback = (isset($_REQUEST['postback']) ? "postback" : "");
        //$className = $action . $postback . "Controller";
        $className = $action."Controller";
        //echo $className ; exit;
        return new $className();
    }

}

abstract class controller {
    abstract function doAction();
    protected function nocache(){
        header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
    }

    protected function __construct() {
        try {
            /* $this->localizationInfo=new localizationClass();
              $this->localize();
             */
        } catch (Exception $e) {
            echo "<pre>";
            print_r($e);
            echo "\n\n\n";
            //print_r($this->template);
            echo "</pre>";
            exit;
        }
    }
    
    protected function get_from_get_post($varname){
        $ret=null;
        $ret=isset($_GET[$varname])?$_GET[$varname]:(isset($_POST[$varname])?$_POST[$varname]:null);
	/*if (!is_null($ret)){
		$ret=urldecode($ret);	
	}*/
        return $ret;
    }
}



abstract class downloadController extends controller {
    protected $content="";
    protected $filename="unknown";
    
    //abstract function setContent();

    public function __construct() {
        parent::__construct();
    }
   

    public function download() {
        try {
            $buffer = ob_get_contents();
            ob_clean();
            ob_start("ob_gzhandler");  // compress page before sending
            $this->nocache();
            header("Content-Type: application/force-download");
	    header("Content-Type: application/octet-stream");
        	header("Content-Type: application/download");
	    header("Content-Disposition: attachment; filename=$this->filename");
           //header("Pragma: no-cache");
		header("Expires: 0");
		echo $this->content;
		exit;
            //echo $buffer;
        } catch (Exception $e) {
            echo "<pre>";
            print_r($e);
            echo "\n\n\n";          
            echo "</pre>";
            exit;
        }
    }

}



abstract class viewcontroller extends controller {
    protected $template = null;
    protected $supportedBrowser=false;
   
    //private $localizedUrlMapping;
    abstract function setTemplateVars();

    public function __construct() {
        parent::__construct();
        require_once INIT::$ROOT.'/inc/PHPTAL/PHPTAL.php';
	$this->supportedBrowser=$this->isSupportedWebBrowser();
        
    }

    private function isSupportedWebBrowser(){
        $browser_info = $this->getBrowser();
        //echo "<pre>"; print_r ($browser_info);
        //print_r($_SERVER);

        $browser_name = strtolower($browser_info['name']);

        //log::doLog('BROWSER NAME: '.$browser_name);

        foreach (INIT::$ENABLED_BROWSERS as $enabled_browser) {
            if (stripos($browser_name, $enabled_browser) !== FALSE) {
                return true;
            }
        }
        return false;


	//$browser_name=strtolower($browser_info['browser']);	
	//if (!in_array($browser_name,INIT::$ENABLED_BROWSERS)){
	//	return false;
	//}
	//return true;
	
    }

   private function getBrowser() {
        $u_agent = $_SERVER['HTTP_USER_AGENT'];
        $bname = 'Unknown';
        $platform = 'Unknown';
        $version = "";

        //First get the platform?
        if (preg_match('/linux/i', $u_agent)) {
            $platform = 'linux';
        } elseif (preg_match('/macintosh|mac os x/i', $u_agent)) {
            $platform = 'mac';
        } elseif (preg_match('/windows|win32/i', $u_agent)) {
            $platform = 'windows';
        }

        // Next get the name of the useragent yes seperately and for good reason
        if (preg_match('/MSIE/i', $u_agent) && !preg_match('/Opera/i', $u_agent)) {
            $bname = 'Internet Explorer';
            $ub = "MSIE";
        } elseif (preg_match('/Firefox/i', $u_agent)) {
            $bname = 'Mozilla Firefox';
            $ub = "Firefox";
        } elseif (preg_match('/Chrome/i', $u_agent)) {
            $bname = 'Google Chrome';
            $ub = "Chrome";
        } elseif (preg_match('/Safari/i', $u_agent)) {
            $bname = 'Apple Safari';
            $ub = "Safari";
        } elseif (preg_match('/Opera/i', $u_agent)) {
            $bname = 'Opera';
            $ub = "Opera";
        } elseif (preg_match('/Netscape/i', $u_agent)) {
            $bname = 'Netscape';
            $ub = "Netscape";
        }

        // finally get the correct version number
        $known = array('Version', $ub, 'other');
        $pattern = '#(?<browser>' . join('|', $known) .
                ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
        if (!preg_match_all($pattern, $u_agent, $matches)) {
            // we have no matching number just continue
        }

        // see how many we have
        $i = count($matches['browser']);
        if ($i != 1) {
            //we will have two since we are not using 'other' argument yet
            //see if version is before or after the name
            if (strripos($u_agent, "Version") < strripos($u_agent, $ub)) {
                $version = $matches['version'][0];
            } else {
                $version = $matches['version'][1];
            }
        } else {
            $version = $matches['version'][0];
        }

        // check if we have a number
        if ($version == null || $version == "") {
            $version = "?";
        }

        return array(
            'userAgent' => $u_agent,
            'name' => $bname,
            'version' => $version,
            'platform' => $platform,
            'pattern' => $pattern
        );
    }
    protected function postback($additionalPostData=array()) {
        $url = $_SERVER['REQUEST_URI'];
        //print_r ($_REQUEST);;
        if (!is_array($additionalPostData)) {
            $additionalPostData = array();
        }
        echo "
		<html> 
			<head> 
				<script type='text/javascript' language='javascript'>
					function submitForm(){
						document.forms[0].submit();
					}
				</script>
			</head> 
			<body onload='submitForm()'> 
				<form id='form' name='form' action='$url' method='post'> 
					<noscript>  
						<div align='center'> 
							<h3>Clicca per continuare </h3> 
							<input type='submit' value='Clicca qui'> 
						</div> 
					</noscript> 
					";
        foreach ($_POST as $k => $v) {
            if ($k != 'action') {
                //$v=urlencode($v);
                $v = stripslashes($v);
                echo "<input type='hidden' name='$k' value='$v'> ";
            }
        }

        foreach ($additionalPostData as $k => $v) {
            if ($k != 'action') {
                //$v=urlencode($v);
                echo "<input type='hidden'name='$k' value='$v'> ";
            }
        }
        if (isset($additionalPostData['action'])) {
            $act = $additionalPostData['action'];
        } else {
            $act = $_POST['action'];
        }
        echo "<input type='hidden'name='action' value='$act'> ";
        echo "</form>
 			</body> 
		</html>
		";
        exit;
    }

    protected function makeTemplate($skeleton_file) {
        try {
            $this->template = new PHPTAL(INIT::$TEMPLATE_ROOT . "/$skeleton_file"); // create a new template object
            $this->template->basepath  = INIT::$BASEURL;
	    $this->template->supportedBrowser=$this->supportedBrowser;
            $this->template->setOutputMode(PHPTAL::HTML5);

		
        } catch (Exception $e) {
            echo "<pre>";
            print_r($e);
            echo "\n\n\n";
            print_r($this->template);
            echo "</pre>";
            exit;
        }
    }
    
    

    public function executeTemplate() {
        $this->setTemplateVars();
        try {
            $buffer = ob_get_contents();
            ob_clean();
            ob_start("ob_gzhandler");  // compress page before sending
            $this->nocache();
            header('Content-Type: text/html; charset=utf-8');          		
            echo $this->template->execute();
            //echo $buffer;
        } catch (Exception $e) {
            echo "<pre>";
            print_r($e);
            echo "\n\n\n";
           // print_r($this->template);
            echo "</pre>";
            exit;
        }
    }

}

abstract class ajaxcontroller extends controller {

    protected $result;

    //abstract function echoJSONResult();
    protected function __construct() {
        parent::__construct();
        $buffer = ob_get_contents();
        ob_clean();
        // ob_start("ob_gzhandler");		// compress page before sending
        header('Content-Type: application/json; charset=utf-8');
        $this->result=array("errors"=>array(), "data"=> array());
    }

    public function echoJSONResult() {
        $toJson = json_encode($this->result);
        echo $toJson;
    }
    
    
    

}

?>
