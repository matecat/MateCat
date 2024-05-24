<?php

namespace FiltersXliffConfig\Filters\DTO;

use JsonSerializable;

class MSExcel implements JsonSerializable
{
    private $extract_doc_properties;
    private $extract_comments;
    private $extract_hidden_cells;
    private $extract_diagrams;
    private $extract_drawings;
    private $extract_sheet_names;
    private $exclude_text_colors;
    private $exclude_columns;

    /**
     * MSExcel constructor.
     * @param bool|null $extract_doc_properties
     * @param bool|null $extract_comments
     * @param bool|null $extract_hidden_cells
     * @param bool|null $extract_diagrams
     * @param bool|null $extract_drawings
     * @param bool|null $extract_sheet_names
     * @param array $exclude_text_colors
     * @param array $exclude_columns
     */
    public function __construct(
        ?bool $extract_doc_properties = null,
        ?bool $extract_comments = null,
        ?bool $extract_hidden_cells = null,
        ?bool $extract_diagrams = null,
        ?bool $extract_drawings = null,
        ?bool $extract_sheet_names = null,
        array $exclude_text_colors = [],
        array $exclude_columns = []
    )
    {
        $this->extract_doc_properties = $extract_doc_properties;
        $this->extract_comments = $extract_comments;
        $this->extract_hidden_cells = $extract_hidden_cells;
        $this->extract_diagrams = $extract_diagrams;
        $this->extract_drawings = $extract_drawings;
        $this->extract_sheet_names = $extract_sheet_names;
        $this->exclude_text_colors = $exclude_text_colors;
        $this->exclude_columns = $exclude_columns;
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'extract_doc_properties' => $this->extract_doc_properties,
            'extract_comments' => $this->extract_comments,
            'extract_hidden_cells' => $this->extract_hidden_cells,
            'extract_diagrams' => $this->extract_diagrams,
            'extract_drawings' => $this->extract_drawings,
            'extract_sheet_names' => $this->extract_sheet_names,
            'exclude_text_colors' => $this->exclude_text_colors,
            'exclude_columns' => $this->exclude_columns,
        ];
    }
}