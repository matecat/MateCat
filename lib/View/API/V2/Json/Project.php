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
use Utils;

class Project {

    /**
     * @var Projects_ProjectStruct[]
     */
    protected $data;

    public function __construct( Projects_ProjectStruct $data = null ) {
        $this->data = $data;
    }

    /**
     * @param $data Projects_ProjectStruct
     *
     * @return array
     */
    public function renderItem( $data ) {
        return array(
                'id'           => (int)$data->id,
                'id_assignee'  => ( is_null( $data->id_assignee ) ? $data->id_assignee : (int)$data->id_assignee ),
                'name'         => $data->name,
                'id_team'      => (int)$data->id_team,
                'project_slug' => Utils::friendly_slug( $data->name )
        );
    }

    public function render() {
        $out = [];

        foreach ( $this->data as $project ) {
            $out[] = $this->renderItem( $project );
        }

        return $out;
    }

}