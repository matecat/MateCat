<?php

namespace Model\Filters\DTO;

/**
 * Extraction parameters for Markdown files.
 *
 * The frontmatter block at the top of a Markdown file is extracted only when
 * `extract_frontmatter` is true. The two key lists choose which frontmatter keys
 * become translatable and are mutually exclusive, as in the YAML and JSON formats:
 * when both are set, `do_not_translate_frontmatter_keys` wins.
 *
 * The keys are YAML mapping keys, which are arbitrary strings, so they are stored
 * verbatim and never trimmed.
 */
class Markdown implements IDto
{

    private bool $extract_frontmatter = false;
    /** @var list<string> */
    private array $translate_frontmatter_keys = [];
    /** @var list<string> */
    private array $do_not_translate_frontmatter_keys = [];

    public function setExtractFrontmatter(bool $extract_frontmatter): void
    {
        $this->extract_frontmatter = $extract_frontmatter;
    }

    /**
     * @param list<string> $translate_frontmatter_keys
     */
    public function setTranslateFrontmatterKeys(array $translate_frontmatter_keys): void
    {
        $this->translate_frontmatter_keys = $translate_frontmatter_keys;
    }

    /**
     * @param list<string> $do_not_translate_frontmatter_keys
     */
    public function setDoNotTranslateFrontmatterKeys(array $do_not_translate_frontmatter_keys): void
    {
        $this->do_not_translate_frontmatter_keys = $do_not_translate_frontmatter_keys;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function fromArray(array $data): void
    {
        if (isset($data['extract_frontmatter'])) {
            $this->setExtractFrontmatter($data['extract_frontmatter']);
        }

        if (isset($data['translate_frontmatter_keys'])) {
            $this->setTranslateFrontmatterKeys($data['translate_frontmatter_keys']);
        }

        if (isset($data['do_not_translate_frontmatter_keys'])) {
            $this->setDoNotTranslateFrontmatterKeys($data['do_not_translate_frontmatter_keys']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $format = [];

        $format['extract_frontmatter'] = $this->extract_frontmatter;
        $format['translate_frontmatter_keys'] = $this->translate_frontmatter_keys;

        if (!empty($this->do_not_translate_frontmatter_keys)) {
            $format['do_not_translate_frontmatter_keys'] = $this->do_not_translate_frontmatter_keys;
            unset($format['translate_frontmatter_keys']);
        }

        return $format;
    }

}
