<?php

namespace Filters\DTO;

use Countable;
use JsonSerializable;

class MSWord implements IDto, JsonSerializable, Countable {

    use DefaultTrait;

    private bool  $extract_doc_properties   = false;
    private bool  $extract_comments         = false;
    private bool  $extract_headers_footers  = false;
    private bool  $extract_hidden_text      = false;
    private bool  $accept_revisions         = false;
    private array $exclude_styles           = [];
    private array $exclude_highlight_colors = [];

    /**
     * @param bool $extract_doc_properties
     */
    public function setExtractDocProperties( bool $extract_doc_properties ): void {
        $this->extract_doc_properties = $extract_doc_properties;
    }

    /**
     * @param bool $extract_comments
     */
    public function setExtractComments( bool $extract_comments ): void {
        $this->extract_comments = $extract_comments;
    }

    /**
     * @param bool $extract_headers_footers
     */
    public function setExtractHeadersFooters( bool $extract_headers_footers ): void {
        $this->extract_headers_footers = $extract_headers_footers;
    }

    /**
     * @param bool $extract_hidden_text
     */
    public function setExtractHiddenText( bool $extract_hidden_text ): void {
        $this->extract_hidden_text = $extract_hidden_text;
    }

    /**
     * @param bool $accept_revisions
     */
    public function setAcceptRevisions( bool $accept_revisions ): void {
        $this->accept_revisions = $accept_revisions;
    }

    /**
     * @param array $exclude_styles
     */
    public function setExcludeStyles( array $exclude_styles ): void {
        $this->exclude_styles = $exclude_styles;
    }

    /**
     * @param array $exclude_highlight_colors
     */
    public function setExcludeHighlightColors( array $exclude_highlight_colors ): void {
        $this->exclude_highlight_colors = $exclude_highlight_colors;
    }

    /**
     * @param $data
     */
    public function fromArray( $data ) {
        if ( isset( $data[ 'extract_doc_properties' ] ) ) {
            $this->setExtractDocProperties( $data[ 'extract_doc_properties' ] );
        }

        if ( isset( $data[ 'extract_comments' ] ) ) {
            $this->setExtractComments( $data[ 'extract_comments' ] );
        }

        if ( isset( $data[ 'accept_revisions' ] ) ) {
            $this->setAcceptRevisions( $data[ 'accept_revisions' ] );
        }

        if ( isset( $data[ 'exclude_highlight_colors' ] ) ) {
            $this->setExcludeHighlightColors( $data[ 'exclude_highlight_colors' ] );
        }

        if ( isset( $data[ 'extract_headers_footers' ] ) ) {
            $this->setExtractHeadersFooters( $data[ 'extract_headers_footers' ] );
        }

        if ( isset( $data[ 'exclude_styles' ] ) ) {
            $this->setExcludeStyles( $data[ 'exclude_styles' ] );
        }

        if ( isset( $data[ 'extract_hidden_text' ] ) ) {
            $this->setExtractHiddenText( $data[ 'extract_hidden_text' ] );
        }
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize() {

        $format = [];

        if ( $this->extract_doc_properties ) {
            $format[ 'extract_doc_properties' ] = $this->extract_doc_properties;
        }

        if ( $this->extract_comments ) {
            $format[ 'extract_comments' ] = $this->extract_comments;
        }

        if ( $this->extract_headers_footers ) {
            $format[ 'extract_headers_footers' ] = $this->extract_headers_footers;
        }

        if ( $this->extract_hidden_text ) {
            $format[ 'extract_hidden_text' ] = $this->extract_hidden_text;
        }

        if ( $this->accept_revisions ) {
            $format[ 'accept_revisions' ] = $this->accept_revisions;
        }

        if ( !empty( $this->exclude_styles ) ) {
            $format[ 'exclude_styles' ] = $this->exclude_styles;
        }

        if ( !empty( $this->exclude_highlight_colors ) ) {
            $format[ 'exclude_highlight_colors' ] = $this->exclude_highlight_colors;
        }

        return $format;

    }

    /**
     * @return int
     */
    public function count(): int {
        return count( $this->jsonSerialize() );
    }

}