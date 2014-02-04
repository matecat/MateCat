<?php

/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 27/01/14
 * Time: 18.56
 *
 */
abstract class viewController extends controller {

    protected $template = null;
    protected $supportedBrowser = false;
    protected $isAuthRequired;
    protected $logged_user = false;

    abstract function setTemplateVars();

    private function getBrowser() {
        $u_agent  = $_SERVER[ 'HTTP_USER_AGENT' ];
        $bname    = 'Unknown';
        $platform = 'Unknown';
        $version  = "";

        //First get the platform?
        if ( preg_match( '/linux/i', $u_agent ) ) {
            $platform = 'linux';
        } elseif ( preg_match( '/macintosh|mac os x/i', $u_agent ) ) {
            $platform = 'mac';
        } elseif ( preg_match( '/windows|win32/i', $u_agent ) ) {
            $platform = 'windows';
        }

        // Next get the name of the useragent yes seperately and for good reason
        if ( preg_match( '/MSIE/i', $u_agent ) && !preg_match( '/Opera/i', $u_agent ) ) {
            $bname = 'Internet Explorer';
            $ub    = "MSIE";
        } elseif ( preg_match( '/Firefox/i', $u_agent ) ) {
            $bname = 'Mozilla Firefox';
            $ub    = "Firefox";
        } elseif ( preg_match( '/Chrome/i', $u_agent ) ) {
            $bname = 'Google Chrome';
            $ub    = "Chrome";
        } elseif ( preg_match( '/Safari/i', $u_agent ) ) {
            $bname = 'Apple Safari';
            $ub    = "Safari";
        } elseif ( preg_match( '/Opera/i', $u_agent ) ) {
            $bname = 'Opera';
            $ub    = "Opera";
        } elseif ( preg_match( '/Netscape/i', $u_agent ) ) {
            $bname = 'Netscape';
            $ub    = "Netscape";
        }

        // finally get the correct version number
        $known   = array( 'Version', $ub, 'other' );
        $pattern = '#(?<browser>' . join( '|', $known ) .
                ')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
        if ( !preg_match_all( $pattern, $u_agent, $matches ) ) {
            // we have no matching number just continue
        }

        // see how many we have
        $i = count( $matches[ 'browser' ] );
        if ( $i != 1 ) {
            //we will have two since we are not using 'other' argument yet
            //see if version is before or after the name
            if ( strripos( $u_agent, "Version" ) < strripos( $u_agent, $ub ) ) {
                $version = $matches[ 'version' ][ 0 ];
            } else {
                $version = $matches[ 'version' ][ 1 ];
            }
        } else {
            $version = $matches[ 'version' ][ 0 ];
        }

        // check if we have a number
        if ( $version == null || $version == "" ) {
            $version = "?";
        }

        return array(
                'userAgent' => $u_agent,
                'name'      => $bname,
                'version'   => $version,
                'platform'  => $platform,
                'pattern'   => $pattern
        );
    }

    public function __construct( $isAuthRequired = false ) {
        parent::__construct();
        $this->supportedBrowser = $this->isSupportedWebBrowser();
        $this->isAuthRequired   = $isAuthRequired;

        //if auth is required, stat procedure
        $this->doAuth();
    }

    private function doAuth() {

        //prepare redirect flag
        $mustRedirectToLogin = false;

        //if no login set and login is required
        if ( !$this->isLoggedIn() and $this->isAuthRequired ) {
            //take note of url we wanted to go after
            $_SESSION[ 'incomingUrl' ] = $_SERVER[ 'REQUEST_URI' ];
            parse_str( $_SERVER[ 'QUERY_STRING' ], $queryStringArray );
            if ( isset( $queryStringArray[ 'new' ] ) ) {
                $_SESSION[ '_newProject' ] = (bool)$queryStringArray[ 'new' ];
            }

            //signal redirection
            $mustRedirectToLogin = true;
        }
        //even if no login in required, if user data is present, pull it out
        if ( !empty( $_SESSION[ 'cid' ] ) ) {
            $this->logged_user = getUserData( $_SESSION[ 'cid' ] );
        }

        if ( $mustRedirectToLogin ) {
            //redirect to login page
            header( 'Location: /login' );
            exit;
        }

        return true;
    }

    public function isLoggedIn() {
        //log::doLog(get_class($this)." requires check for login");
        $username_from_cookie = AuthCookie::getCredentials();
        if ( $username_from_cookie ) {
            $_SESSION[ 'cid' ] = $username_from_cookie;
        }

        return ( isset( $_SESSION[ 'cid' ] ) && !empty( $_SESSION[ 'cid' ] ) );
    }

    private function isSupportedWebBrowser() {
        $browser_info = $this->getBrowser();
        $browser_name = strtolower( $browser_info[ 'name' ] );

        foreach ( INIT::$ENABLED_BROWSERS as $enabled_browser ) {
            if ( stripos( $browser_name, $enabled_browser ) !== false ) {
                return true;
            }
        }

        return false;
    }

    protected function postback( $additionalPostData = array() ) {
        $url = $_SERVER[ 'REQUEST_URI' ];
        if ( !is_array( $additionalPostData ) ) {
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
        foreach ( $_POST as $k => $v ) {
            if ( $k != 'action' ) {
                //$v=urlencode($v);
                $v = stripslashes( $v );
                echo "<input type='hidden' name='$k' value='$v'> ";
            }
        }

        foreach ( $additionalPostData as $k => $v ) {
            if ( $k != 'action' ) {
                //$v=urlencode($v);
                echo "<input type='hidden'name='$k' value='$v'> ";
            }
        }
        if ( isset( $additionalPostData[ 'action' ] ) ) {
            $act = $additionalPostData[ 'action' ];
        } else {
            $act = $_POST[ 'action' ];
        }
        echo "<input type='hidden'name='action' value='$act'> ";
        echo "</form>
			</body>
			</html>
			";
        exit;
    }

    protected function makeTemplate( $skeleton_file ) {
        try {
            $this->template                   = new PHPTAL( INIT::$TEMPLATE_ROOT . "/$skeleton_file" ); // create a new template object
            $this->template->basepath         = INIT::$BASEURL;
            $this->template->hostpath         = INIT::$HTTPHOST;
            $this->template->supportedBrowser = $this->supportedBrowser;
            $this->template->enabledBrowsers  = INIT::$ENABLED_BROWSERS;
            $this->template->setOutputMode( PHPTAL::HTML5 );
        } catch ( Exception $e ) {
            echo "<pre>";
            print_r( $e );
            echo "\n\n\n";
            print_r( $this->template );
            echo "</pre>";
            exit;
        }
    }

    public function finalize() {
        $this->setTemplateVars();
        try {
            $buffer = ob_get_contents();
            ob_get_clean();
            ob_start( "ob_gzhandler" ); // compress page before sending
            $this->nocache();

            header( 'Content-Type: text/html; charset=utf-8' );
            echo $this->template->execute();
        } catch ( Exception $e ) {
            echo "<pre>";
            print_r( $e );
            echo "\n\n\n";
            echo "</pre>";
            exit;
        }
    }

}
