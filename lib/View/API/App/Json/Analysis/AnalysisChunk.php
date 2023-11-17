<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 13/11/23
 * Time: 19:08
 *
 */

namespace API\App\Json\Analysis;

use Chunks_ChunkStruct;
use Engine;
use Exception;
use Jobs_JobStruct;
use JsonSerializable;
use TmKeyManagement_Filter;
use Url\JobUrlBuilder;
use Users_UserStruct;

class AnalysisChunk implements JsonSerializable {

    /**
     * @var AnalysisFile[]
     */
    protected $files = null;
    /**
     * @var Jobs_JobStruct
     */
    protected $chunkStruct;
    /**
     * @var mixed
     */
    protected $projectName;
    /**
     * @var Users_UserStruct
     */
    protected $user;

    public function __construct( Jobs_JobStruct $chunkStruct, $projectName, Users_UserStruct $user ) {
        $this->chunkStruct = $chunkStruct;
        $this->projectName = $projectName;
        $this->user        = $user;
    }

    /**
     * @param AnalysisFile $file
     *
     * @return $this
     */
    public function setFile( AnalysisFile $file ) {
        $this->files[ $file->getId() ] = $file;

        return $this;
    }

    public function jsonSerialize() {
        return [
                'password'    => $this->chunkStruct->password,
                'status'      => $this->chunkStruct->status,
                'engines'     => $this->getEngines(),
                'memory_keys' => $this->getMemoryKeys(),
                'urls'        => JobUrlBuilder::createFromJobStructAndProjectName( $this->chunkStruct, $this->projectName )->getUrls(),
                'files'       => array_values( $this->files )
        ];
    }

    /**
     * @return Jobs_JobStruct
     */
    public function getChunkStruct() {
        return $this->chunkStruct;
    }
    
    /**
     * @return string
     */
    public function getPassword() {
        return $this->chunkStruct->password;
    }

    /**
     * @param $id
     *
     * @return bool
     */
    public function hasFile( $id ) {
        return array_key_exists( $id, $this->files );
    }

    /**
     * @throws Exception
     */
    private function getEngines() {
        $tmEngine = Engine::getInstance( $this->chunkStruct->id_tms );
        $mtEngine = Engine::getInstance( $this->chunkStruct->id_mt_engine );

        return [
                'tm' => ( new \API\V2\Json\Engine() )->renderItem( $tmEngine->getEngineRow() ),
                'mt' => ( new \API\V2\Json\Engine() )->renderItem( $mtEngine->getEngineRow() )
        ];
    }

    /**
     * @return array
     * @throws Exception
     */
    private function getMemoryKeys() {
        $tmKeys  = [];
        $jobKeys = $this->chunkStruct->getClientKeys( $this->user, TmKeyManagement_Filter::OWNER )[ 'job_keys' ];

        foreach ( $jobKeys as $tmKey ) {
            $tmKeys[][ trim( $tmKey->name ) ] = trim( $tmKey->key );
        }

        return $tmKeys;
    }

}