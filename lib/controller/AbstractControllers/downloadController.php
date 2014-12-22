<?php
/**
 * Created by PhpStorm.
 * User: domenico
 * Date: 27/01/14
 * Time: 18.57
 * 
 */

abstract class downloadController extends controller {

    protected $content = "";
    protected $filename = "unknown";

    protected function unlockToken(){

        if( isset( $this->downloadToken ) && !empty( $this->downloadToken )){
            setcookie(
                    $this->downloadToken,
                    $this->downloadToken,
                    2147483647            // expires January 1, 2038
            );

        }

    }

    public function finalize() {
        try {

            $this->unlockToken();

            $buffer = ob_get_contents();
            ob_get_clean();
            ob_start("ob_gzhandler");  // compress page before sending
            $this->nocache();
            header("Content-Type: application/force-download");
            header("Content-Type: application/octet-stream");
            header("Content-Type: application/download");
            header("Content-Disposition: attachment; filename=\"$this->filename\""); // enclose file name in double quotes in order to avoid duplicate header error. Reference https://github.com/prior/prawnto/pull/16
            header("Expires: 0");
            header("Connection: close");
            echo $this->content;
            exit;
        } catch (Exception $e) {
            echo "<pre>";
            print_r($e);
            echo "\n\n\n";
            echo "</pre>";
            exit;
        }
    }

}