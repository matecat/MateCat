<?php

class Engines_Results_DeepL_TranslateResponse
{
    public $id;
    public $create_date;
    public $segment;
    public $raw_segment;
    public $translation;
    public $source_note;
    public $target_note;
    public $raw_translation;
    public $quality;
    public $reference;
    public $usage_count;
    public $subject;
    public $created_by;
    public $last_updated_by;
    public $last_update_date;
    public $match;
    public $memory_key;
    public $ICE;
    public $tm_properties;
    public $target;
    public $source;

    public function __construct($translation, $source, $target, $segment)
    {
        $this->id = 0;
        $this->create_date = '0000-00-00';
        $this->segment = $segment;
        $this->raw_segment = $segment;
        $this->translation = $translation;
        $this->raw_translation = $translation;
        $this->source_note = $translation;
        $this->target_note = $translation;
        $this->quality = 85;
        $this->reference = '';
        $this->usage_count = '';
        $this->subject = '';
        $this->created_by = 'MT-DeepL';
        $this->last_updated_by = '';
        $this->last_update_date = '';
        $this->match = 'MT-DeepL';
        $this->memory_key = '';
        $this->ICE = false;
        $this->tm_properties = [];
        $this->target = $target;
        $this->source = $source;
    }

    public function toJson()
    {
        return [
            'id' => $this->id,
            'create_date' => $this->create_date,
            'segment' => $this->segment,
            'raw_segment' => $this->raw_segment,
            'translation' => $this->translation,
            'raw_translation' => $this->raw_translation,
            'source_note' => $this->source_note,
            'target_note' => $this->target_note,
            'quality' => $this->quality,
            'reference' => $this->reference,
            'usage_count' => $this->usage_count,
            'subject' => $this->subject,
            'created_by' => $this->created_by,
            'last_updated_by' => $this->last_updated_by,
            'last_update_date' => $this->last_update_date,
            'match' => $this->match,
            'memory_key' => $this->memory_key,
            'ICE' => $this->ICE,
            'tm_properties' => $this->tm_properties,
            'target' => $this->target,
            'source' => $this->source,
        ];
    }
}