<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 06/07/2017
 * Time: 16:53
 */

namespace Features\Dqf\Utils;

use Features\Dqf\Model\CachedAttributes\ContentType;
use Features\Dqf\Model\CachedAttributes\Industry;
use Features\Dqf\Model\CachedAttributes\Process;
use Features\Dqf\Model\CachedAttributes\QualityLevel;
use INIT;
use PHPTALWithAppend;

class Functions {


    public static function commonVarsForDecorator( PHPTALWithAppend $template ) {
        $template->dqf_enabled       = true ;
        $template->dqf_content_types = (new ContentType())->getArray();
        $template->dqf_industry      = (new Industry())->getArray();
        $template->dqf_process       = (new Process())->getArray();
        $template->dqf_quality_level = (new QualityLevel())->getArray();
    }

    public static function scopeId( $id ) {
        return INIT::$DQF_ID_PREFIX . '-' . $id ;
    }

    public static function descope( $id ) {
        return str_replace( static::scopeId(''), '', $id ) ;
    }

    public static function mapMtEngine( $input ) {
        // TODO: ...
        return $input ;
    }

}