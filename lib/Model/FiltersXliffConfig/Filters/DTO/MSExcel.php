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
     * @param bool|null $extract_doc_properties
     */
    public function setExtractDocProperties(?bool $extract_doc_properties): void
    {
        $this->extract_doc_properties = $extract_doc_properties;
    }

    /**
     * @param bool|null $extract_comments
     */
    public function setExtractComments(?bool $extract_comments): void
    {
        $this->extract_comments = $extract_comments;
    }

    /**
     * @param bool|null $extract_hidden_cells
     */
    public function setExtractHiddenCells(?bool $extract_hidden_cells): void
    {
        $this->extract_hidden_cells = $extract_hidden_cells;
    }

    /**
     * @param bool|null $extract_diagrams
     */
    public function setExtractDiagrams(?bool $extract_diagrams): void
    {
        $this->extract_diagrams = $extract_diagrams;
    }

    /**
     * @param bool|null $extract_drawings
     */
    public function setExtractDrawings(?bool $extract_drawings): void
    {
        $this->extract_drawings = $extract_drawings;
    }

    /**
     * @param bool|null $extract_sheet_names
     */
    public function setExtractSheetNames(?bool $extract_sheet_names): void
    {
        $this->extract_sheet_names = $extract_sheet_names;
    }

    /**
     * @param array $exclude_text_colors
     */
    public function setExcludeTextColors(array $exclude_text_colors): void
    {
        $this->exclude_text_colors = $exclude_text_colors;
    }

    /**
     * @param array $exclude_columns
     */
    public function setExcludeColumns(array $exclude_columns): void
    {
        $this->exclude_columns = $exclude_columns;
    }

    /**
     * @param $data
     */
    public function fromArray($data)
    {
        if(isset($data['exclude_columns'])){
            $this->setExcludeColumns($data['exclude_columns']);
        }

        if(isset($data['extract_diagrams'])){
            $this->setExtractDiagrams($data['extract_diagrams']);
        }

        if(isset($data['extract_comments'])){
            $this->setExtractComments($data['extract_comments']);
        }

        if(isset($data['extract_drawings'])){
            $this->setExtractDrawings($data['extract_drawings']);
        }

        if(isset($data['extract_hidden_cells'])){
            $this->setExtractHiddenCells($data['extract_hidden_cells']);
        }

        if(isset($data['exclude_text_colors'])){
            $this->setExcludeTextColors($data['exclude_text_colors']);
        }

        if(isset($data['extract_doc_properties'])){
            $this->setExtractDocProperties($data['extract_doc_properties']);
        }

        if(isset($data['extract_sheet_names'])){
            $this->setExtractSheetNames($data['extract_sheet_names']);
        }
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