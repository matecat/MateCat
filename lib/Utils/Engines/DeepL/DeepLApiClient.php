<?php

namespace Utils\Engines\DeepL;

use DeepL\DeepLException;
use DeepL\GlossaryEntries;
use DeepL\GlossaryInfo;
use DeepL\Translator;
use DeepL\TranslatorOptions;

class DeepLApiClient
{
    private Translator $translator;

    /**
     * @param string $apiKey
     *
     * @return DeepLApiClient
     * @throws DeepLApiException
     */
    public static function newInstance(string $apiKey): DeepLApiClient
    {
        return new self($apiKey);
    }

    /**
     * DeepLApiClient constructor.
     *
     * @param string $apiKey
     * @param Translator|null $translator Optional translator instance (for testing)
     * @throws DeepLApiException
     */
    private function __construct(string $apiKey, ?Translator $translator = null)
    {
        if ($translator !== null) {
            $this->translator = $translator;
            return;
        }

        try {
            $this->translator = new Translator($apiKey, [
                TranslatorOptions::TIMEOUT => 30,
            ]);
        } catch (DeepLException $e) {
            throw new DeepLApiException($e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Create an instance with a custom Translator (for testing purposes).
     *
     * @param Translator $translator
     * @return DeepLApiClient
     * @throws DeepLApiException
     */
    public static function newInstanceWithTranslator(Translator $translator): DeepLApiClient
    {
        return new self('unused', $translator);
    }

    /**
     * @param string $text
     * @param string $sourceLang
     * @param string $targetLang
     * @param string|null $formality
     * @param string|null $idGlossary
     *
     * @return array<string, array<int, array<string, string|null>>>
     * @throws DeepLApiException
     */
    public function translate(string $text, string $sourceLang, string $targetLang, ?string $formality = null, ?string $idGlossary = null): array
    {
        try {
            $options = [];

            if ($formality) {
                $options['formality'] = $formality;
            }

            if ($idGlossary) {
                $options['glossary'] = $idGlossary;
            }

            $result = $this->translator->translateText($text, $sourceLang, $targetLang, $options);

            return [
                'translations' => [
                    [
                        'detected_source_language' => $result->detectedSourceLang,
                        'text' => $result->text,
                    ]
                ]
            ];
        } catch (DeepLException $e) {
            throw new DeepLApiException($e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     * @throws DeepLApiException
     */
    public function allGlossaries(): array
    {
        try {
            $glossaries = $this->translator->listGlossaries();

            return [
                'glossaries' => array_map(fn(GlossaryInfo $g) => $this->glossaryInfoToArray($g), $glossaries)
            ];
        } catch (DeepLException $e) {
            throw new DeepLApiException($e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     * @throws DeepLApiException
     */
    public function createGlossary(array $data): array
    {
        try {
            $name = $data['name'];
            $sourceLang = $data['source_lang'];
            $targetLang = $data['target_lang'];
            $entriesFormat = $data['entries_format'] ?? 'tsv';

            if ($entriesFormat === 'csv' && is_array($data['entries'])) {
                // Convert 2D array of [source, target] pairs to CSV string
                $csvLines = array_map(fn($row) => implode(',', $row), $data['entries']);
                $csvContent = implode("\n", $csvLines);
                $glossaryInfo = $this->translator->createGlossaryFromCsv($name, $sourceLang, $targetLang, $csvContent);
            } elseif ($entriesFormat === 'tsv') {
                $entries = is_string($data['entries'])
                    ? GlossaryEntries::fromTsv($data['entries'])
                    : GlossaryEntries::fromEntries($data['entries']);
                $glossaryInfo = $this->translator->createGlossary($name, $sourceLang, $targetLang, $entries);
            } else {
                // Fallback: send entries as CSV string
                $csvContent = is_array($data['entries']) ? implode("\n", array_map(fn($row) => implode(',', $row), $data['entries'])) : $data['entries'];
                $glossaryInfo = $this->translator->createGlossaryFromCsv($name, $sourceLang, $targetLang, $csvContent);
            }

            return $this->glossaryInfoToArray($glossaryInfo);
        } catch (DeepLException $e) {
            throw new DeepLApiException($e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * @param string $id
     *
     * @return array<string, string>
     * @throws DeepLApiException
     */
    public function deleteGlossary(string $id): array
    {
        try {
            $this->translator->deleteGlossary($id);

            return ['id' => $id];
        } catch (DeepLException $e) {
            throw new DeepLApiException($e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * @param string $id
     *
     * @return array<string, mixed>
     * @throws DeepLApiException
     */
    public function getGlossary(string $id): array
    {
        try {
            return $this->glossaryInfoToArray($this->translator->getGlossary($id));
        } catch (DeepLException $e) {
            throw new DeepLApiException($e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * @param string $id
     *
     * @return array<string, string>
     * @throws DeepLApiException
     */
    public function getGlossaryEntries(string $id): array
    {
        try {
            $entries = $this->translator->getGlossaryEntries($id);

            return $entries->getEntries();
        } catch (DeepLException $e) {
            throw new DeepLApiException($e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Convert a GlossaryInfo object to an associative array.
     *
     * @return array<string, mixed>
     */
    private function glossaryInfoToArray(GlossaryInfo $info): array
    {
        return [
            'glossary_id' => $info->glossaryId,
            'name' => $info->name,
            'ready' => $info->ready,
            'source_lang' => $info->sourceLang,
            'target_lang' => $info->targetLang,
            'creation_time' => $info->creationTime->format('Y-m-d\TH:i:s.u\Z'),
            'entry_count' => $info->entryCount,
        ];
    }
}

