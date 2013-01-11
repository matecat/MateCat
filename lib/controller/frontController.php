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
	$browser_info=get_browser(NULL,true);
//	echo "<pre>"; print_r ($browser_info);exit;
	$browser_name=strtolower($browser_info['browser']);	
	if (!in_array($browser_name,INIT::$ENABLED_BROWSERS)){
		return false;
	}
	return true;
	
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
