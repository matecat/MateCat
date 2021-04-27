<?php

set_time_limit( 180 );

class xliffToTargetController extends downloadController {

    protected $error;
    protected $errorMessage;

    public function doAction() {
        // Just add the XLIFF extension, the XliffProprietaryDetect class needs it
        $file_path       = $_FILES['xliff']['tmp_name'] . '.xlf';
        move_uploaded_file($_FILES['xliff']['tmp_name'], $file_path);

        $conversion = Filters::xliffToTarget(array(
          array(
            'document_content' => file_get_contents($file_path))
        ));
        $conversion = $conversion[0];

        if ($conversion['isSuccess'] === true) {
            $this->outputContent = json_encode(array(
              "fileName" => (isset($conversion['fileName']) ? $conversion['fileName'] : $conversion['filename']),
              "fileContent" => base64_encode($conversion['document_content']),
              "size" => filesize($file_path),
              "type" => mime_content_type($file_path),
              "message" => "File downloaded! Check your download folder"
            ));
            $this->_filename     = $conversion['fileName'];
        } else {
            $this->error = true;
            $this->errorMessage =  $conversion[0]['errorMessage'];
        }
    }

    public function finalize() {
        if ($this->error === true) {
            http_response_code(500);
            if (empty($this->errorMessage)) {
                echo "(No error message provided)";
            } else {
                echo $this->errorMessage;
            }
            exit;
        } else {
            try {
                $buffer = ob_get_clean();
                ob_start("ob_gzhandler");  // compress page before sending
                $this->nocache();
                header("Content-Type: application/force-download");
                header("Content-Type: application/octet-stream");
                header("Content-Type: application/download");
                header("Content-Disposition: attachment; filename=\"$this->_filename\""); // enclose file name in double quotes in order to avoid duplicate header error. Reference https://github.com/prior/prawnto/pull/16
                header("Expires: 0");
                header("Connection: close");
                echo $this->outputContent;
                exit;
            } catch (Exception $e) {
                echo "<pre>";
                print_r($e);
                echo "\n\n\n</pre>";
                exit;
            }
        }
    }

}
