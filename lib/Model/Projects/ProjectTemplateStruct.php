<?php

namespace Projects;

use DataAccess_AbstractDaoSilentStruct;
use DataAccess_IDaoStruct;
use JsonSerializable;
use stdClass;

class ProjectTemplateStruct extends DataAccess_AbstractDaoSilentStruct implements DataAccess_IDaoStruct, JsonSerializable {
    public ?int    $id                       = null;
    public string  $name                     = "";
    public bool    $is_default               = false;
    public int     $uid                      = 0;
    public int     $id_team                  = 0;
    public bool    $speech2text              = false;
    public bool    $lexica                   = true;
    public bool    $tag_projection           = true;
    public ?string $cross_language_matches   = null;
    public ?string $segmentation_rule        = null;
    public ?string $mt                       = null;
    public ?string $tm                       = null;
    public int     $payable_rate_template_id = 0;
    public int     $qa_model_template_id     = 0;
    public int     $filters_template_id      = 0;
    public int     $xliff_config_template_id = 0;
    public bool    $pretranslate_100         = false;
    public bool    $pretranslate_101         = false;
    public bool    $get_public_matches       = true;
    public string  $created_at;
    public ?string $modified_at              = null;
    public int     $dictation                = 0;
    public int     $show_whitespace          = 0;
    public int     $character_counter        = 0;
    public int     $ai_assistant             = 0;
    public int     $team_id                  = 0;
    public ?string $subject                  = null;
    public ?string $source_language          = null;
    public ?string $target_language          = null;

    /**
     * @param string   $json
     * @param int      $uid
     * @param int|null $id
     *
     * @return $this
     */
    public function hydrateFromJSON( string $json, int $uid, ?int $id = null ): ProjectTemplateStruct {
        $json = json_decode( $json );

        $this->id                       = $json->id ?? $id;
        $this->uid                      = $json->uid ?? $uid;
        $this->name                     = $json->name;
        $this->is_default               = ( isset( $json->is_default ) ) ? $json->is_default : false;
        $this->id_team                  = $json->id_team;
        $this->speech2text              = $json->speech2text;
        $this->lexica                   = $json->lexica;
        $this->tag_projection           = $json->tag_projection;
        $this->cross_language_matches   = json_encode( $json->cross_language_matches );
        $this->segmentation_rule        = json_encode( $json->segmentation_rule );
        $this->pretranslate_100         = $json->pretranslate_100;
        $this->pretranslate_101         = $json->pretranslate_101;
        $this->get_public_matches       = $json->get_public_matches;
        $this->mt                       = json_encode( $json->mt );
        $this->tm                       = json_encode( $json->tm );
        $this->payable_rate_template_id = $json->payable_rate_template_id;
        $this->qa_model_template_id     = $json->qa_model_template_id;
        $this->filters_template_id      = $json->filters_template_id;
        $this->xliff_config_template_id = $json->xliff_config_template_id;
        $this->dictation                = $json->dictation;
        $this->show_whitespace          = $json->show_whitespace;
        $this->character_counter        = $json->character_counter;
        $this->ai_assistant             = $json->ai_assistant;
        $this->team_id                  = $json->team_id;
        $this->subject                  = $json->subject;
        $this->source_language          = $json->source_language;
        $this->target_language          = (!empty($json->target_language)) ? serialize($json->target_language) : null;

        return $this;
    }

    /**
     * @return ?object
     */
    public function getCrossLanguageMatches(): ?object {
        if ( !empty( $this->cross_language_matches ) ) {
            return json_decode( $this->cross_language_matches );
        }

        return new stdClass();
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

    public function getTargetLanguage(): array {
        return unserialize($this->target_language);
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array {
        return [
                'id'                       => (int)$this->id,
                'name'                     => $this->name,
                'is_default'               => $this->is_default,
                'uid'                      => $this->uid,
                'id_team'                  => $this->id_team,
                'speech2text'              => $this->speech2text,
                'lexica'                   => $this->lexica,
                'tag_projection'           => $this->tag_projection,
                'cross_language_matches'   => $this->getCrossLanguageMatches(),
                'segmentation_rule'        => $this->getSegmentationRule(),
                'mt'                       => $this->getMt(),
                'tm'                       => $this->getTm(),
                'payable_rate_template_id' => $this->payable_rate_template_id ? (int)$this->payable_rate_template_id : 0,
                'qa_model_template_id'     => $this->qa_model_template_id ? (int)$this->qa_model_template_id : 0,
                'filters_template_id'      => $this->filters_template_id ? (int)$this->filters_template_id : 0,
                'xliff_config_template_id' => $this->xliff_config_template_id ? (int)$this->xliff_config_template_id : 0,
                'get_public_matches'       => $this->get_public_matches,
                'pretranslate_100'         => $this->pretranslate_100,
                'pretranslate_101'         => $this->pretranslate_101,
                'dictation'                => $this->dictation,
                'show_whitespace'          => $this->show_whitespace,
                'character_counter'        => $this->character_counter,
                'ai_assistant'             => $this->ai_assistant,
                'team_id'                  => $this->team_id,
                'subject'                  => $this->subject,
                'source_language'          => $this->source_language,
                'target_language'          => $this->getTargetLanguage(),
                'created_at'               => date_create( $this->created_at )->format( DATE_RFC822 ),
                'modified_at'              => date_create( $this->modified_at )->format( DATE_RFC822 ),
        ];
    }
}