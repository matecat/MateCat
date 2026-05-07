<?php

namespace Model\Filters\DTO;

class MSExcel implements IDto
{

    private bool $extract_doc_properties = false;
    private bool $extract_hidden_cells = false;
    private bool $extract_diagrams = false;
    private bool $extract_drawings = false;
    private bool $extract_sheet_names = false;
    /** @var list<string> */
    private array $exclude_columns = [];

    public function setExtractDocProperties(bool $extract_doc_properties): void
    {
        $this->extract_doc_properties = $extract_doc_properties;
    }

    public function setExtractHiddenCells(bool $extract_hidden_cells): void
    {
        $this->extract_hidden_cells = $extract_hidden_cells;
    }

    public function setExtractDiagrams(bool $extract_diagrams): void
    {
        $this->extract_diagrams = $extract_diagrams;
    }

    public function setExtractDrawings(bool $extract_drawings): void
    {
        $this->extract_drawings = $extract_drawings;
    }

    public function setExtractSheetNames(bool $extract_sheet_names): void
    {
        $this->extract_sheet_names = $extract_sheet_names;
    }

    /**
     * @param list<string> $exclude_columns
     */
    public function setExcludeColumns(array $exclude_columns): void
    {
        $this->exclude_columns = $exclude_columns;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function fromArray(array $data): void
    {
        if (isset($data['exclude_columns'])) {
            $this->setExcludeColumns($data['exclude_columns']);
        }

        if (isset($data['extract_diagrams'])) {
            $this->setExtractDiagrams($data['extract_diagrams']);
        }

        if (isset($data['extract_drawings'])) {
            $this->setExtractDrawings($data['extract_drawings']);
        }

        if (isset($data['extract_hidden_cells'])) {
            $this->setExtractHiddenCells($data['extract_hidden_cells']);
        }


        if (isset($data['extract_doc_properties'])) {
            $this->setExtractDocProperties($data['extract_doc_properties']);
        }

        if (isset($data['extract_sheet_names'])) {
            $this->setExtractSheetNames($data['extract_sheet_names']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $format = [];

        $format['extract_doc_properties'] = $this->extract_doc_properties;
        $format['extract_hidden_cells'] = $this->extract_hidden_cells;
        $format['extract_diagrams'] = $this->extract_diagrams;
        $format['extract_drawings'] = $this->extract_drawings;
        $format['extract_sheet_names'] = $this->extract_sheet_names;
        $format['exclude_columns'] = $this->exclude_columns;

        return $format;
    }

}
