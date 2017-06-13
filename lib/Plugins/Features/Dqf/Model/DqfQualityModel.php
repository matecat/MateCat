<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 06/06/2017
 * Time: 11:28
 */

namespace Features\Dqf\Model;

use Features\Dqf\Service\Struct\Request\ReviewSettingsRequestStruct;
use LQA\CategoryStruct;
use LQA\ModelStruct;
use Projects_ProjectStruct;

class DqfQualityModel {

    /**
     * @var ModelStruct
     */
    protected $qaModelStruct ;

    /**
     * @var CategoryStruct[]
     */
    protected $matecat_categories ;

    /**
     * @var array
     */
    protected $severities ;

    public function __construct( Projects_ProjectStruct $project ) {
        $this->qaModelStruct = $project->getLqaModel() ;
    }

    public function getReviewSettings() {
        $this->matecat_categories = $this->qaModelStruct->getCategories();

        $struct = new ReviewSettingsRequestStruct();
        $struct->reviewType = 'error_typology';
        $struct->severityWeights = $this->getSeverities() ;
        $struct->sampling = 20 ;
        $struct->passFailThreshold = 200 ;

        foreach( $this->matecat_categories as $category ) {
            $options = json_decode( $category->options, true );
            $struct->addErrorCategory( $options['dqf_id'] ) ;
        }

        return $struct ;
    }

    /**
     * In DQF all severities are the same for all categories, so we find the
     * severties defined in the first record and use those.
     *
     */
    protected function getSeverities() {
        $severities = [] ;
        foreach( $this->matecat_categories[ 0 ]->getJsonSeverities() as $severity ) {
            $severities[] = [
                    'severityId' => $severity['dqf_id'],
                    'weight' => $severity['penalty']
            ];
        }
        return json_encode( $severities );
    }

}
