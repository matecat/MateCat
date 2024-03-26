<?php
/**
 * Created by PhpStorm.
 * @author hashashiyyin domenico@translated.net / ostico@gmail.com
 * Date: 13/11/23
 * Time: 19:08
 *
 */

namespace API\App\Json\Analysis;

use Engine;
use Exception;
use Jobs_JobStruct;
use JsonSerializable;
use TmKeyManagement_Filter;
use Url\JobUrlBuilder;
use Users_UserStruct;

class AnalysisChunk implements JsonSerializable {

    /**
     * @var AnalysisJobSummary
     */
    protected $summary = null;

    /**
     * @var AnalysisFile[]
     */
    protected $files = [];
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

    /**
     * @var int
     */
    protected $total_raw = 0;
    /**
     * @var int
     */
    protected $total_equivalent = 0;
    /**
     * @var int
     */
    protected $total_industry = 0;

    public function __construct( Jobs_JobStruct $chunkStruct, $projectName, Users_UserStruct $user ) {
        $this->chunkStruct = $chunkStruct;
        $this->projectName = $projectName;
        $this->user        = $user;
        $this->summary     = new AnalysisJobSummary();
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

    /**
     * @throws Exception
     */
    public function jsonSerialize() {
        return [
                'password'         => $this->chunkStruct->password,
                'status'           => $this->chunkStruct->status,
                'engines'          => $this->getEngines(),
                'memory_keys'      => $this->getMemoryKeys(),
                'urls'             => JobUrlBuilder::createFromJobStructAndProjectName( $this->chunkStruct, $this->projectName )->getUrls(),
                'files'            => array_values( $this->files ),
                'summary'          => $this->summary,
                'total_raw'        => $this->total_raw,
                'total_equivalent' => round( $this->total_equivalent ),
                'total_industry'   => round( $this->total_industry ),
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
     * @return AnalysisFile[]|null
     */
    public function getFiles() {
        return $this->files;
    }

    /**
     * @throws Exception
     */
    private function getEngines() {

        // this can happen even when fast analysis is not completed
        if ( !is_numeric( $this->chunkStruct->id_tms ) || !is_numeric( $this->chunkStruct->id_mt_engine ) ) {
            return [];
        }

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
        $tmKeys = [];

        // this can happen even when fast analysis is not completed
        if ( empty( $this->chunkStruct->tm_keys ) ) {
            return $tmKeys;
        }

        $jobKeys = $this->chunkStruct->getClientKeys( $this->user, TmKeyManagement_Filter::OWNER )[ 'job_keys' ];

        foreach ( $jobKeys as $tmKey ) {
            $tmKeys[][ trim( $tmKey->name ) ] = trim( $tmKey->key );
        }

        return $tmKeys;
    }

    /**
     * @return AnalysisJobSummary
     */
    public function getSummary() {
        return $this->summary;
    }

    /**
     * @param $raw
     *
     * @return void
     */
    public function incrementRaw( $raw ) {
        $this->total_raw += (int)$raw;
    }

    /**
     * @param $equivalent
     *
     * @return void
     */
    public function incrementEquivalent( $equivalent ) {
        $this->total_equivalent += $equivalent;
    }

    /**
     * @param $industry
     *
     * @return void
     */
    public function incrementIndustry( $industry ) {
        $this->total_industry += $industry;
    }

}