<?php

namespace Model\Projects;

use JsonSerializable;
use Model\DataAccess\AbstractDaoSilentStruct;
use Model\DataAccess\IDaoStruct;
use stdClass;

class ProjectTemplateStruct extends AbstractDaoSilentStruct implements IDaoStruct, JsonSerializable {
    public ?int    $id                           = null;
    public string  $name                         = "";
    public bool    $is_default                   = false;
    public int     $uid                          = 0;
    public int     $id_team                      = 0;
    public bool    $tag_projection               = true;
    public ?string $segmentation_rule            = null;
    public ?string $mt                           = null;
    public ?string $tm                           = null;
    public int     $public_tm_penalty            = 0;
    public int     $payable_rate_template_id     = 0;
    public int     $qa_model_template_id         = 0;
    public int     $filters_template_id          = 0;
    public int     $xliff_config_template_id     = 0;
    public bool    $pretranslate_100             = false;
    public bool    $pretranslate_101             = false;
    public bool    $tm_prioritization            = false;
    public bool    $dialect_strict               = false;
    public bool    $get_public_matches           = true;
    public string  $created_at;
    public ?string $modified_at                  = null;
    public ?string $subject                      = null;
    public ?string $source_language              = null;
    public ?string $target_language              = null;
    public bool    $character_counter_count_tags = false;
    public ?string $character_counter_mode       = null;
    public ?string $subfiltering_handlers        = null;
    public ?int    $mt_quality_value_in_editor   = null;

    /**
     * @param object   $decodedObject
     * @param int      $uid
     * @param int|null $id
     *
     * @return $this
     */
    public function hydrateFromJSON( object $decodedObject, int $uid, ?int $id = null ): ProjectTemplateStruct {

        $this->id                           = $decodedObject->id ?? $id;
        $this->uid                          = $decodedObject->uid ?? $uid;
        $this->name                         = $decodedObject->name;
        $this->is_default                   = ( isset( $decodedObject->is_default ) ) ? $decodedObject->is_default : false;
        $this->id_team                      = $decodedObject->id_team;
        $this->segmentation_rule            = ( !empty( $decodedObject->segmentation_rule ) ) ? json_encode( $decodedObject->segmentation_rule ) : null;
        $this->pretranslate_100             = $decodedObject->pretranslate_100;
        $this->pretranslate_101             = $decodedObject->pretranslate_101;
        $this->tm_prioritization            = $decodedObject->tm_prioritization;
        $this->dialect_strict               = $decodedObject->dialect_strict;
        $this->public_tm_penalty            = $decodedObject->public_tm_penalty ?? 0;
        $this->get_public_matches           = $decodedObject->get_public_matches;
        $this->mt                           = json_encode( $decodedObject->mt );
        $this->tm                           = ( !empty( $decodedObject->tm ) ) ? json_encode( $decodedObject->tm ) : null;
        $this->payable_rate_template_id     = $decodedObject->payable_rate_template_id;
        $this->qa_model_template_id         = $decodedObject->qa_model_template_id;
        $this->filters_template_id          = $decodedObject->filters_template_id;
        $this->xliff_config_template_id     = $decodedObject->xliff_config_template_id;
        $this->character_counter_count_tags = $decodedObject->character_counter_count_tags;
        $this->character_counter_mode       = $decodedObject->character_counter_mode;
        $this->subject                      = $decodedObject->subject;
        $this->subfiltering_handlers        = json_encode( $decodedObject->subfiltering_handlers ?? null );
        $this->source_language              = $decodedObject->source_language;
        $this->target_language              = ( !empty( $decodedObject->target_language ) ) ? serialize( $decodedObject->target_language ) : null;
        $this->mt_quality_value_in_editor   = ( !empty( $decodedObject->mt_quality_value_in_editor ) ) ? (int)$decodedObject->mt_quality_value_in_editor : null;

        return $this;
    }

    /**
     * @return object
     */
    public function getSegmentationRule(): object {
        if ( !empty( $this->segmentation_rule ) ) {
            return json_decode( $this->segmentation_rule );
        }

        return new stdClass();
    }

    /**
     * @return object
     */
    public function getMt(): object {
        if ( !empty( $this->mt ) ) {
            return json_decode( $this->mt );
        }

        return new stdClass();
    }

    /**
     * @return array
     */
    public function getTm(): array {
        if ( !empty( $this->tm ) ) {
            return json_decode( $this->tm );
        }

        return [];
    }

    /**
     * @return array
     */
    public function getTargetLanguage(): array {

        if ( empty( $this->target_language ) ) {
            return [];
        }

        return unserialize( $this->target_language );
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array {
        return [
                'id'                               => (int)$this->id,
                'name'                             => $this->name,
                'is_default'                       => $this->is_default,
                'uid'                              => $this->uid,
                'id_team'                          => $this->id_team,
                'segmentation_rule'                => $this->getSegmentationRule(),
                'mt'                               => $this->getMt(),
                'tm'                               => $this->getTm(),
                'payable_rate_template_id'         => $this->payable_rate_template_id ?: 0,
                'qa_model_template_id'             => $this->qa_model_template_id ?: 0,
                'filters_template_id'              => $this->filters_template_id ?: 0,
                'xliff_config_template_id'         => $this->xliff_config_template_id ?: 0,
                'get_public_matches'               => $this->get_public_matches,
                'public_tm_penalty'                => $this->public_tm_penalty ?: 0,
                'pretranslate_100'                 => $this->pretranslate_100,
                'pretranslate_101'                 => $this->pretranslate_101,
                'tm_prioritization'                => $this->tm_prioritization,
                'dialect_strict'                   => $this->dialect_strict,
                'mt_quality_value_in_editor'       => $this->mt_quality_value_in_editor,
                'character_counter_count_tags'     => $this->character_counter_count_tags,
                'character_counter_mode'           => $this->character_counter_mode,
                'subject'                          => $this->subject,
                MetadataDao::SUBFILTERING_HANDLERS => json_decode( $this->subfiltering_handlers, true ),
                'source_language'                  => $this->source_language,
                'target_language'                  => $this->getTargetLanguage(),
                'created_at'                       => date_create( $this->created_at )->format( DATE_RFC822 ),
                'modified_at'                      => date_create( $this->modified_at )->format( DATE_RFC822 ),
        ];
    }
}