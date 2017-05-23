<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/04/17
 * Time: 21.42
 *
 */

namespace API\V2\Json;


use API\App\Json\OutsourceConfirmation;
use CatUtils;
use Jobs_JobStruct;
use Langs_Languages;
use ManageUtils;
use WordCount_Struct;

class JobAnonymous extends Job {

    /**
     * @param $jStruct Jobs_JobStruct
     *
     * @return array
     */
    public function renderItem( Jobs_JobStruct $jStruct ) {

        $jobJson = parent::renderItem( $jStruct );

        unset( $jobJson[ 'translator' ] );
        unset( $jobJson[ 'owner' ] );
        unset( $jobJson[ 'private_tm_key' ] );

        return $jobJson;

    }

}