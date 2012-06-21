<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
define ('DEFAULT_NUM_RESULTS',2);
class ssetTranslationController extends ajaxcontroller{
    private $id_job;
    private $id_segment;
    private $id_translator;
    private $status;
    private $time_to_edit;
    private $translation;
    
    
    public function __construct() {
        parent::__construct();        
        
        $this->id_job=$this->get_from_get_post('id_job');       
        $this->id_segment=$this->get_from_get_post('id_segment');       
        $this->id_translator=$this->get_from_get_post('id_translator');       
        $this->status=$this->get_from_get_post('status');       
        $this->time_to_edit=$this->get_from_get_post('time_to_edit');       
        $this->translation=$this->get_from_get_post('translation'); 
    }
    
    public function doAction(){
//        if (empty($this->id_segment)){
//            $this->result['error'][]=array("code"=>-1, "message"=>"missing id_segment");
//        }
//        if (empty($this->id_segment)){
//            $this->result['error'][]=array("code"=>-2, "message"=>"missing text");
//        }
//        
//        if (empty($this->num_results)){
//            $this->num_results=DEFAULT_NUM_RESULTS;
//        }
//        
//        if (!empty ($this->result['error'])){
//            return -1;
//        }
//        

        $this->result['code']=1;        
        $this->result['data']="OK";        
    }




}
?>
