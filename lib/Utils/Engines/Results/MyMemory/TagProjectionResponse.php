<?php

/**
 * Created by PhpStorm.
 * User: Hashashiyyin
 * Date: 17/05/16
 * Time: 11:49
 */

namespace Utils\Engines\Results\MyMemory;

use Exception;
use Matecat\SubFiltering\MateCatFilter;
use Model\FeaturesBase\FeatureSet;
use Model\Projects\MetadataDao;
use Utils\Engines\Results\TMSAbstractResponse;

class TagProjectionResponse extends TMSAbstractResponse {

    /**
     * TagProjectionResponse constructor.
     *
     * @param          $response
     * @param array    $dataRefMap
     * @param int|null $id_project
     *
     * @throws Exception
     */
    public function __construct( $response, array $dataRefMap = [], ?int $id_project = null ) {
        $featureSet = ( $this->featureSet !== null ) ? $this->featureSet : new FeatureSet();
        /**
         * @var MateCatFilter $Filter
         */
        $handlerTagNames = [];

        if(!empty($id_project)){
            $metadataDao = new MetadataDao();
            $handlerTagNames = $metadataDao->getSubfilteringCustomHandlers((int)$id_project);
        }

        $Filter             = MateCatFilter::getInstance( $featureSet, null, null, $dataRefMap, $handlerTagNames );
        $this->responseData = isset( $response[ 'data' ][ 'translation' ] ) ? $Filter->fromLayer0ToLayer2( $response[ 'data' ][ 'translation' ] ) : '';
    }

}