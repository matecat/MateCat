<?php

namespace Model\Jobs;

use Exception;

class ChunkOptionsSanitizer
{

    /** @var array<string, mixed> */
    private array $options;
    /** @var array<string, mixed> */
    private array $sanitized = [];

    private ?string $source_lang = null;
    /** @var list<string> */
    private array $target_lang = [];

    /** @var list<string> */
    private array $boolean_keys = ['speech2text', 'lexiqa', 'tag_projection'];

    /** @var list<string> */
    public static array $lexiQA_allowed_languages = [
        'af-ZA',
        'sq-AL',
        'ar-SA',
        'hy-AM',
        'as-IN',
        'az-AZ',
        'fr-BE',
        'bn-IN',
        'be-BY',
        'bs-BA',
        'bg-BG',
        'my-MM',
        'ca-ES',
        'zh-CN',
        'zh-TW',
        'zh-HK',
        'hr-HR',
        'cs-CZ',
        'da-DK',
        'nl-NL',
        'en-GB',
        'en-US',
        'en-AU',
        'en-CA',
        'en-IN',
        'en-IE',
        'en-NZ',
        'en-SG',
        'et-EE',
        'fi-FI',
        'nl-BE',
        'fr-FR',
        'fr-CA',
        'fr-CH',
        'de-DE',
        'ka-GE',
        'el-GR',
        'gu-IN',
        'hi-IN',
        'hu-HU',
        'id-ID',
        'it-IT',
        'ja-JP',
        'jv-ID',
        'ha-NG',
        'he-IL',
        'ht-HT',
        'kk-KZ',
        'rn-BI',
        'ko-KR',
        'ky-KG',
        'lv-LV',
        'lt-LT',
        'mk-MK',
        'ms-MY',
        'mr-IN',
        'ne-NP',
        'nb-NO',
        'fa-IR',
        'pl-PL',
        'pt-PT',
        'pt-BR',
        'ro-RO',
        'ru-RU',
        'sr-Latn-RS',
        'sr-Cyrl-RS',
        'si-LK',
        'sk-SK',
        'sl-SI',
        'es-ES',
        'es-CO',
        'es-MX',
        'es-US',
        'es-419',
        'sw-KE',
        'sv-SE',
        'de-CH',
        'tl-PH',
        'ta-LK',
        'ta-IN',
        'th-TH',
        'tr-TR',
        'uk-UA',
        'ur-PK',
        'uz-UZ',
        'vi-VN'

    ];
    /**
     * All combinations of languages for Tag Projection
     *
     * @var array<string, string>
     */
    public static array $tag_projection_allowed_languages = [
        'en-de' => 'English - German',
        'en-es' => 'English - Spanish',
        'en-fr' => 'English - French',
        'en-it' => 'English - Italian',
        'en-pt' => 'English - Portuguese',
        'en-ru' => 'English - Russian',
        'en-cs' => 'English - Czech',
        'en-nl' => 'English - Dutch',
        'en-fi' => 'English - Finnish',
        'en-pl' => 'English - Polish',
        'en-da' => 'English - Danish',
        'en-sv' => 'English - Swedish',
        'en-el' => 'English - Greek',
        'en-hu' => 'English - Hungarian',
        'en-lt' => 'English - Lithuanian',
        'en-ja' => 'English - Japanese',
        'en-et' => 'English - Estonian',
        'en-sk' => 'English - Slovak',
        'en-bg' => 'English - Bulgarian',
        'en-bs' => 'English - Bosnian',
        'en-ar' => 'English - Arabic',
        'en-ca' => 'English - Catalan',
        'en-zh' => 'English - Chinese',
        'en-he' => 'English - Hebrew',
        'en-hr' => 'English - Croatian',
        'en-id' => 'English - Indonesian',
        'en-is' => 'English - Icelandic',
        'en-ko' => 'English - Korean',
        'en-lv' => 'English - Latvian',
        'en-mk' => 'English - Macedonian',
        'en-ms' => 'English - Malay',
        'en-mt' => 'English - Maltese',
        'en-nb' => 'English - Norwegian Bokmål',
        'en-nn' => 'English - Norwegian Nynorsk',
        'en-ro' => 'English - Romanian',
        'en-sl' => 'English - Slovenian',
        'en-sq' => 'English - Albanian',
        'en-sr' => 'English - Montenegrin',
        'en-th' => 'English - Thai',
        'en-tr' => 'English - Turkish',
        'en-uk' => 'English - Ukrainian',
        'en-vi' => 'English - Vietnamese',
        'de-it' => 'German - Italian',
        'de-fr' => 'German - French',
        'de-cs' => 'German - Czech',
        'fr-it' => 'French - Italian',
        'fr-nl' => 'French - Dutch',
        'it-es' => 'Italian - Spanish',
        'da-sv' => 'Danish - Swedish',
        'nl-pt' => 'Dutch - Portuguese',
        'nl-fi' => 'Dutch - Finnish',
        'zh-en' => 'Chinese - English',
        'sv-da' => 'Swedish - Danish',
        'cs-de' => 'Czech - German',
    ];


    /**
     * @param array<string, mixed> $input_options
     */
    public function __construct(array $input_options)
    {
        $this->options = $input_options;
    }

    /**
     * @param list<string> $target
     */
    public function setLanguages(string $source, array $target): void
    {
        $this->source_lang = $source;
        $this->target_lang = $target;
    }

    /**
     * This method populates an array of sanitized input options. Known keys are sanitized.
     * Unknown keys are let as they are and copied to the sanitized array.
     *
     * @return array<string, mixed>
     * @throws Exception
     */
    public function sanitize(): array
    {
        $this->sanitized = $this->options;

        if (isset($this->options['speech2text'])) {
            $this->sanitizeSpeech2Text();
        }

        if (isset($this->options['tag_projection'])) {
            $this->sanitizeTagProjection();
        }

        if (isset($this->options['lexiqa'])) {
            $this->sanitizeLexiQA();
        }

        $this->sanitizeSegmentationRule();

        $this->convertBooleansToInt();

        return $this->sanitized;
    }

    private function convertBooleansToInt(): void
    {
        foreach ($this->boolean_keys as $key) {
            if (isset($this->sanitized [$key])) {
                $this->sanitized[$key] = (int)$this->sanitized[$key];
            }
        }
    }

    private function sanitizeSegmentationRule(): void
    {
        $rules = ['patent', 'paragraph'];

        if (
            isset($this->options['segmentation_rule']) &&
            in_array($this->options['segmentation_rule'], $rules)
        ) {
            $this->sanitized['segmentation_rule'] = $this->options['segmentation_rule'];
        } else {
            unset($this->sanitized['segmentation_rule']);
        }
    }

    // No special sanitization for the speech2text field is required
    private function sanitizeSpeech2Text(): void
    {
        $this->sanitized['speech2text'] = !!$this->options['speech2text'];
    }

    /**
     * If Lexiqa is requested to be enabled, then check if the language is in combination
     * @throws Exception
     */
    private function sanitizeLexiQA(): void
    {
        $this->sanitized['lexiqa'] = ($this->options['lexiqa'] == true && $this->checkSourceAndTargetAreInCombination(self::$lexiQA_allowed_languages));
    }

    /**
     * If the tag projection is requested to be enabled, check if the language combination is allowed.
     * @throws Exception
     */
    private function sanitizeTagProjection(): void
    {
        $this->sanitized['tag_projection'] = ($this->options['tag_projection'] == true && $this->checkSourceAndTargetAreInCombinationForTagProjection(self::$tag_projection_allowed_languages));
    }

    /**
     * Check that the source language AND at least one target language are both
     * present in the allowed list.
     *
     * @param list<string> $langs
     * @throws Exception
     */
    private function checkSourceAndTargetAreInCombination(array $langs): bool
    {
        $this->__ensureLanguagesAreSet();

        $allowedSet = array_flip($langs);

        if (!isset($allowedSet[$this->source_lang])) {
            return false;
        }

        foreach ($this->target_lang as $target) {
            if (isset($allowedSet[$target])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, string> $langs
     * @throws Exception
     */
    private function checkSourceAndTargetAreInCombinationForTagProjection(array $langs): bool
    {
        $this->__ensureLanguagesAreSet();

        $lang_combination = [];
        $found = false;
        foreach ($this->target_lang ?? [] as $value) {
            $lang_combination[] = explode('-', $value)[0] . '-' . explode('-', $this->source_lang ?? '')[0];
            $lang_combination[] = explode('-', $this->source_lang ?? '')[0] . '-' . explode('-', $value)[0];
        }

        foreach ($lang_combination as $langPair) {
            if (array_key_exists($langPair, $langs)) {
                $found = true;
                break;
            }
        }

        return $found;
    }

    /**
     * @throws Exception
     */
    private function __ensureLanguagesAreSet(): void
    {
        if (empty($this->target_lang) || empty($this->source_lang)) {
            throw  new Exception('Trying to sanitize options, but languages are not set');
        }
    }

}