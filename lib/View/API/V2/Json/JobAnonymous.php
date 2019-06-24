<?php
/**
 * Created by PhpStorm.
 * @author domenico domenico@translated.net / ostico@gmail.com
 * Date: 14/04/17
 * Time: 21.42
 *
 */

namespace API\V2\Json;


use Chunks_ChunkStruct;
use Users_UserStruct;

class JobAnonymous extends Job {

    /**
     * @param Users_UserStruct $user
     *
     * @return $this
     */
    public function setUser( Users_UserStruct $user ) {
        return $this;
    }

    /**
     * @param bool $called_from_api
     *
     * @return $this
     */
    public function setCalledFromApi( $called_from_api ) {
        return $this;
    }

    /**
     * @param                         $chunk Chunks_ChunkStruct
     *
     * @param \Projects_ProjectStruct $project
     * @param \FeatureSet             $featureSet
     *
     * @return array
     * @throws \Exception
     */
    public function renderItem( Chunks_ChunkStruct $chunk, \Projects_ProjectStruct $project, \FeatureSet $featureSet ) {

        $jobJson = parent::renderItem( $chunk, $project, $featureSet );

        unset( $jobJson[ 'translator' ] );
        unset( $jobJson[ 'owner' ] );
        unset( $jobJson[ 'private_tm_key' ] );

        return $jobJson;

    }

}