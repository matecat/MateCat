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
use ReflectionException;

class ProjectAnonymous extends Project {

    /** @noinspection PhpMissingParentConstructorInspection */

    /**
     * Project constructor.
     *
     * @param Projects_ProjectStruct[] $data
     */
    public function __construct( array $data = null ) {
        $this->data      = $data;
        $this->jRenderer = new JobAnonymous();
    }

    /**
     * @param bool $called_from_api
     *
     * @return $this
     */
    public function setCalledFromApi( bool $called_from_api ): Project {
        return $this;
    }

    /**
     * @param $project Projects_ProjectStruct
     *
     * @return array
     * @throws ReflectionException
     */
    public function renderItem( Projects_ProjectStruct $project ): array {

        $projectOutputFields = parent::renderItem( $project );
        unset( $projectOutputFields[ 'id_team' ] );
        unset( $projectOutputFields[ 'id_assignee' ] );

        return $projectOutputFields;

    }

}