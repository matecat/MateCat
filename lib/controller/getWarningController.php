<?
include_once INIT::$MODEL_ROOT . "/queries.php";

class getWarningController extends ajaxcontroller{	

	private $id_job;

	public function __destruct(){}

	public function  __construct() {
		parent::__construct();	
		$this->id_job = $this->get_from_get_post('id_job');
	}

        /**
         * Return to Javascript client the error list in the form:
         * 
         * <pre>
         * array(
         *       [total] => 1
         *       [details] => Array
         *       (
         *           [0] => Array
         *               (
         *                   [id_segment] => 2224860
         *                   [warning] => 5
         *               )
         *       )
         * )
         * </pre>
         * 
         * $query are in the form:
         * <pre>
         * Array
         * (
         * [0] => Array
         *     (
         *         [total] => 2
         *         [id_segment] => 
         *         [serialized_errors_list] => [{"outcome":3,"debug":"bad target xml"}]
         *     ),
         * [1] => Array
         *     (
         *         [total] => 1
         *         [id_segment] => 2224896
         *         [serialized_errors_list] => 01
         *     ),
         * [2] => Array
         *     (
         *         [total] => 1
         *         [id_segment] => 2224903
         *         [serialized_errors_list] => [{"outcome":3,"debug":"bad target xml"}]
         *     ),
         * )
         * </pre>
         */
	public function doAction (){
		
            $result = getWarning($this->id_job);
            $_total = array_shift($result);
            $this->result['total'] = (int)$_total['total'];

// php 5.2 lacks of lambda functions
//            array_walk( $result, function( &$item, $key ) {
//                if( $item['warnings'] == '01' ){
//                    //backward compatibility
//                    //TODO Remove after some days/month/year of use of QA class. 
//                    $item['debug'] = '[{"outcome":3,"debug":"bad target xml"}]';
//                }
//                unset($item['total']);
//            } );

            $_keys = array();
            foreach( $result as $key => &$item ) {
                if( $item['warnings'] == '01' || $item['warnings'] == "" ){
                    //backward compatibility
                    //TODO Remove after some days/month/year of use of QA class. 
                    $item['warnings'] = '[{"outcome":3,"debug":"bad target xml"}]';
                }
                unset($item['total']);
                $_keys[] = $item['id_segment'];
            }

            $result = @array_combine( $_keys , $result );                
            $this->result['details'] = $result;
                
	}
}
?>