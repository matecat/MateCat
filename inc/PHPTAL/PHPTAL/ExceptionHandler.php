<?php
/**
 * PHPTAL templating engine
 *
 * PHP Version 5
 *
 * @category HTML
 * @package  PHPTAL
 * @author   Kornel Lesiński <kornel@aardvarkmedia.co.uk>
 * @license  http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 * @version  SVN: $Id: $
 * @link     http://phptal.org/
 */

class PHPTAL_ExceptionHandler
{
    private $encoding;
    function __construct($encoding)
    {
        $this->encoding = $encoding;
    }

    /**
     * PHP's default exception handler allows error pages to be indexed and can reveal too much information,
     * so if possible PHPTAL sets up its own handler to fix this.
     *
     * Doesn't change exception handler if non-default one is set.
     *
     * @param Exception e exception to re-throw and display
     *
     * @return void
     * @throws Exception
     */
    public static function handleException(Exception $e, $encoding)
    {
        // PHPTAL's handler is only useful on fresh HTTP response
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            $old_exception_handler = set_exception_handler(array(new PHPTAL_ExceptionHandler($encoding), '_defaultExceptionHandler'));

            if ($old_exception_handler !== NULL) {
                restore_exception_handler(); // if there's user's exception handler, let it work
            }
        }
        throw $e; // throws instead of outputting immediately to support user's try/catch
    }


    /**
     * Generates simple error page. Sets appropriate HTTP status to prevent page being indexed.
     *
     * @param Exception e exception to display
     */
    public function _defaultExceptionHandler($e)
    {
        if (!headers_sent()) {
            header('HTTP/1.1 500 PHPTAL Exception');
            header('Content-Type:text/html;charset='.$this->encoding);
        }

        $line = $e->getFile();
        if ($e->getLine()) {
            $line .= ' line '.$e->getLine();
        }

        if (ini_get('display_errors')) {
            $title = get_class($e).': '.htmlspecialchars($e->getMessage());
            $body = "<p><strong>\n".htmlspecialchars($e->getMessage()).'</strong></p>' .
                    '<p>In '.htmlspecialchars($line)."</p><pre>\n".htmlspecialchars($e->getTraceAsString()).'</pre>';
        } else {
            $title = "PHPTAL Exception";
            $body = "<p>This page cannot be displayed.</p><hr/>" .
                    "<p><small>Enable <code>display_errors</code> to see detailed message.</small></p>";
        }

        echo "<!DOCTYPE html><html xmlns='http://www.w3.org/1999/xhtml'><head><style>body{font-family:sans-serif}</style><title>\n";
        echo $title.'</title></head><body><h1>PHPTAL Exception</h1>'.$body;
        error_log($e->getMessage().' in '.$line);
        echo '</body></html>'.str_repeat('    ', 100)."\n"; // IE won't display error pages < 512b
        exit(1);
    }
}
