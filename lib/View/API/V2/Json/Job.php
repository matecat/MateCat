<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/04/17
 * Time: 21.42
 *
 */

namespace API\V2\Json;


use Jobs_JobStruct;
use Translators\JobsTranslatorsStruct;

class Job {

    /**
     * @param $jStruct Jobs_JobStruct
     *
     * @param $jTranslatorsStruct JobsTranslatorsStruct
     *
     * @return array
     */
    public function renderItem( Jobs_JobStruct $jStruct, JobsTranslatorsStruct $jTranslatorsStruct = null ) {
        return array(
                'id'         => (int)$jStruct->id,
                'password'   => $jStruct->password,
                'source'     => $jStruct->source,
                'target'     => $jStruct->target,
                'owner'      => $jStruct->owner,
                'translator' => ( !empty( $jTranslatorsStruct ) ? ( new JobTranslator() )->renderItem( $jTranslatorsStruct ) : [] )
        );
    }

}