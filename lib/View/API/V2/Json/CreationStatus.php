<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 03/03/17
 * Time: 20.06
 *
 */

namespace API\V2\Json;


class CreationStatus {

    private $data;

    public function __construct( $data ) {
        $this->data = $data;
    }

    public function render() {
        return [
                'status'       => 200,
                'message'      => 'Project created',
                'id_project'   => (int)$this->data->id_project,
                'project_pass' => (isset($this->data) and isset($this->data->ppassword)) ? $this->data->ppassword : null,
                'project_name' => (isset($this->data) and isset($this->data->project_name)) ? $this->data->project_name : null,
                'new_keys'     => (isset($this->data) and isset($this->data->new_keys)) ? $this->data->new_keys : null,
                'analyze_url'  => (isset($this->data) and isset($this->data->analyze_url)) ? $this->data->analyze_url : null,
        ];
    }

}
