<?php

namespace Filters\DTO;

use JsonSerializable;

class MSExcel implements IDto, JsonSerializable {

    private bool  $extract_doc_properties = false;
    private bool  $extract_hidden_cells   = false;
    private bool  $extract_diagrams       = false;
    private bool  $extract_drawings       = false;
    private bool  $extract_sheet_names    = false;
    private array $exclude_columns        = [];

    /**
     * @param bool $extract_doc_properties
     */
    public function setExtractDocProperties( bool $extract_doc_properties ): void {
        $this->extract_doc_properties = $extract_doc_properties;
    }

    /**
     * @param bool $extract_hidden_cells
     */
    public function setExtractHiddenCells( bool $extract_hidden_cells ): void {
        $this->extract_hidden_cells = $extract_hidden_cells;
    }

    /**
     * @param bool $extract_diagrams
     */
    public function setExtractDiagrams( bool $extract_diagrams ): void {
        $this->extract_diagrams = $extract_diagrams;
    }

    /**
     * @param bool $extract_drawings
     */
    public function setExtractDrawings( bool $extract_drawings ): void {
        $this->extract_drawings = $extract_drawings;
    }

    /**
     * @param bool $extract_sheet_names
     */
    public function setExtractSheetNames( bool $extract_sheet_names ): void {
        $this->extract_sheet_names = $extract_sheet_names;
    }

    /**
     * @param array $exclude_columns
     */
    public function setExcludeColumns( array $exclude_columns ): void {
        $this->exclude_columns = $exclude_columns;
    }

    /**
     * @param $data
     */
    public function fromArray( $data ) {
        if ( isset( $data[ 'exclude_columns' ] ) ) {
            $this->setExcludeColumns( $data[ 'exclude_columns' ] );
        }

        if ( isset( $data[ 'extract_diagrams' ] ) ) {
            $this->setExtractDiagrams( $data[ 'extract_diagrams' ] );
        }

        if ( isset( $data[ 'extract_drawings' ] ) ) {
            $this->setExtractDrawings( $data[ 'extract_drawings' ] );
        }

        if ( isset( $data[ 'extract_hidden_cells' ] ) ) {
            $this->setExtractHiddenCells( $data[ 'extract_hidden_cells' ] );
        }


        if ( isset( $data[ 'extract_doc_properties' ] ) ) {
            $this->setExtractDocProperties( $data[ 'extract_doc_properties' ] );
        }

        if ( isset( $data[ 'extract_sheet_names' ] ) ) {
            $this->setExtractSheetNames( $data[ 'extract_sheet_names' ] );
        }
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize() {

        $format = [];

        $format[ 'extract_doc_properties' ] = $this->extract_doc_properties;
        $format[ 'extract_hidden_cells' ]   = $this->extract_hidden_cells;
        $format[ 'extract_diagrams' ]       = $this->extract_diagrams;
        $format[ 'extract_drawings' ]       = $this->extract_drawings;
        $format[ 'extract_sheet_names' ]    = $this->extract_sheet_names;
        $format[ 'exclude_columns' ]        = $this->exclude_columns;

        return $format;

    }

}