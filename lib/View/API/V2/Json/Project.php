<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/01/17
 * Time: 16.41
 *
 */

namespace API\V2\Json;


use Projects_ProjectStruct;

class Project {

    /**
     * @var Projects_ProjectStruct[]
     */
    protected $data ;

    public function __construct( Projects_ProjectStruct $data = null ) {
        $this->data = $data;
    }

    /**
     * @param $data Projects_ProjectStruct
     * @return array
     */
    public function renderItem( $data ) {
        return array(
            'id'              => (int) $data->id ,
            'id_assignee'     => (int) $data->id_assignee,
            'name'            => $data->name,
            'id_workspace'    => (int) $data->id_workspace,
            'id_organization' => (int) $data->id_organization,
        );
    }

    public function render() {
        $out = [] ;

        foreach( $this->data as $project ) {
            $out[] = $this->renderItem( $project ) ;
        }

        return $out ;
    }

}