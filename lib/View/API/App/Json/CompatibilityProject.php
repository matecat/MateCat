<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 20/01/17
 * Time: 16.41
 *
 */

namespace API\App\Json;

use API\V2\Json\Job;
use API\V2\Json\Project;
use Chunks_ChunkStruct;
use Constants_JobStatus;
use Projects_ProjectStruct;
use Utils;

/**
 * ( 2023/11/06 )
 *
 * This class is meant to allow back compatibility with running projects
 * after the advancement word-count switch from weighted to raw
 *
 * YYY [Remove] backward compatibility for current projects
 * YYY Remove after a reasonable amount of time
 */
class CompatibilityProject extends Project {

    /**
     * Project constructor.
     *
     * @param Projects_ProjectStruct[] $data
     * @param string                   $search_status
     */
    public function __construct( array $data = [], $search_status = null ) {

        parent::__construct();

        $this->data = $data;
        $this->status = $search_status;
        $jRendered = new CompatibilityJob();

        if($search_status){
            $jRendered->setStatus($search_status);
        }

        $this->jRenderer = $jRendered;
    }

}