<?php

namespace FiltersXliffConfig\Filters\DTO;

use JsonSerializable;

class MSWord implements JsonSerializable
{
    private $extract_doc_properties;
    private $extract_comments;
    private $extract_headers_footers;
    private $extract_hidden_text;
    private $accept_revisions;
    private $exclude_styles;
    private $exclude_text_colors;
    private $exclude_highlight_colors;

    /**
     * MSWord constructor.
     * @param bool|null $extract_doc_properties
     * @param bool|null $extract_comments
     * @param bool|null $extract_headers_footers
     * @param bool|null $extract_hidden_text
     * @param bool|null $accept_revisions
     * @param array $exclude_styles
     * @param array $exclude_text_colors
     * @param array $exclude_highlight_colors
     */
    public function __construct(
        ?bool $extract_doc_properties = null,
        ?bool $extract_comments = null,
        ?bool $extract_headers_footers = null,
        ?bool $extract_hidden_text = null,
        ?bool $accept_revisions = null,
        array $exclude_styles = [],
        array $exclude_text_colors = [],
        array $exclude_highlight_colors = []
    )
    {
        $this->extract_doc_properties = $extract_doc_properties;
        $this->extract_comments = $extract_comments;
        $this->extract_headers_footers = $extract_headers_footers;
        $this->extract_hidden_text = $extract_hidden_text;
        $this->accept_revisions = $accept_revisions;
        $this->exclude_styles = $exclude_styles;
        $this->exclude_text_colors = $exclude_text_colors;
        $this->exclude_highlight_colors = $exclude_highlight_colors;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'extract_doc_properties' => $this->extract_doc_properties,
            'extract_comments' => $this->extract_comments,
            'extract_headers_footers' => $this->extract_headers_footers,
            'extract_hidden_text' => $this->extract_hidden_text,
            'accept_revisions' => $this->accept_revisions,
            'exclude_styles' => $this->exclude_styles,
            'exclude_text_colors' => $this->exclude_text_colors,
            'exclude_highlight_colors' => $this->exclude_highlight_colors,
        ];
    }
}