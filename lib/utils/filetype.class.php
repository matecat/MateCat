<?php
class filetype {

    private $extension;
    private $type = null;

    public function __construct($ext) {
        if (empty($ext)) {
            throw new Exception("No extension specified");
        }
        $this->extension = strtolower($ext);
        switch ($this->extension) {
            case 'doc':
                $this->type = 'MSWORD_DOC';
                break;
            case 'docx':
                $this->type = 'MSWORD_DOCX';
                break;
            case 'txt':
            	$this->type = 'PLAINTEXT';
		break;
            case 'xlf':   // Just until we support tag management
            case 'xliff': // Just until we support tag management
            case 'sdlxliff': // Just until we support tag management
                $this->type = 'SDLXLIFF';
                break;  
            case 'html':
            case 'htm':
            case 'php':
                $this->type = 'HTML';
                break;
            
            default:
                log::doLog("Unrecognized extension $this->extension : assign default  PLAINTEXT");
                $this->type = 'PLAINTEXT';
        }
    }

    public function parse($text) {
        //  !!!!! NOTES  !!!!!
        // DOC - DOCX
            //1 - I'm changing the sequence xC2xA0 (MS WORD NON BREAKING SPACE) into whitespace. 
            //the question is: how can I come back to the correct charcode ? 
            // 
            // it could be better to use a subsitute structure like <span class='substitute_word_160'> </span>
         
          switch ($this->type) {
          
            case 'MSWORD_DOC':                
                $text = preg_replace('/\xC2\xA0/', " ", $text); // substitute MS WORD non breaking space A0. 
                break;
            
             case 'MSWORD_DOCX':                
                $text = preg_replace('/\xC2\xA0/', " ", $text); // substitute MS WORD non breaking space A0. 
                
             
                break;
        }
        return $text;
    }

}

?>
