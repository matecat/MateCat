<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 27/07/2017
 * Time: 17:37
 */

namespace Features\Dqf\Decorator;


use AbstractDecorator;
use Features\Dqf\Model\CachedAttributes\ContentType;
use Features\Dqf\Model\CachedAttributes\Industry;
use Features\Dqf\Model\CachedAttributes\Process;
use Features\Dqf\Model\CachedAttributes\QualityLevel;

class NewProjectDecorator extends AbstractDecorator {

    /**
     * @var \PHPTALWithAppend
     */
    protected $template ;

    public function decorate() {
        $this->template->dqf_enabled = true ;
        $this->template->dqf_content_types = (new ContentType())->getArray();
        $this->template->dqf_industry = (new Industry())->getArray();
        $this->template->dqf_process = (new Process())->getArray();
        $this->template->dqf_quality_level = (new QualityLevel())->getArray();
    }

}