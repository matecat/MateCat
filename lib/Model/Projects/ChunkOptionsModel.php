<?php


namespace Projects;

use Exception;
use Jobs_JobStruct;
use ProjectOptionsSanitizer;
use Projects_MetadataDao;
use ReflectionException;

class ChunkOptionsModel {

    private Jobs_JobStruct $chunk;

    public static array $valid_keys = [
            'speech2text', 'tag_projection', 'lexiqa'
    ];

    private array $received_options = [];
    public array  $project_metadata = [];

    public function __construct( Jobs_JobStruct $chunk ) {
        $this->chunk            = $chunk;
        $this->project_metadata = $chunk->getProject()->getMetadataAsKeyValue();
    }

    /**
     * @throws Exception
     */
    public function isEnabled( string $key ): int {
        $value = $this->getByChunkOrProjectOption( $key );

        $sanitizer = new ProjectOptionsSanitizer( [ $key => $value ] );
        $sanitizer->setLanguages( $this->chunk->source, [ $this->chunk->target] );

        $sanitized = $sanitizer->sanitize();

        return $sanitized[ $key ];

    }

    /**
     * @throws Exception
     */
    public function setOptions( $options ) {
        $filtered = array_intersect_key( $options, array_flip( self::$valid_keys ) );

        $sanitizer = new ProjectOptionsSanitizer( $filtered );
        $sanitizer->setLanguages( $this->chunk->source, [ $this->chunk->target] );

        $sanitized = $sanitizer->sanitize();

        $this->received_options = array_merge(
                $filtered,
                $sanitized
        );
    }

    /**
     * @throws ReflectionException
     */
    public function save() {
        if ( empty( $this->received_options ) ) {
            return;
        }

        $dao = new Projects_MetadataDao();

        foreach ( $this->received_options as $key => $value ) {
            $dao->set( $this->chunk->id_project, Projects_MetadataDao::buildChunkKey( $key, $this->chunk ), $value );
        }

        $this->project_metadata = $this->chunk->getProject()->getMetadataAsKeyValue();
    }

    /**
     * @throws Exception
     */
    public function toArray(): array {
        $out = [];

        foreach ( static::$valid_keys as $name ) {
            $out[ $name ] = $this->isEnabled( $name );
        }

        return $out;
    }

    /**
     * @param $key
     *
     * @return bool
     */
    private function getByChunkOrProjectOption( $key ): bool {
        $chunk_key = Projects_MetadataDao::buildChunkKey( $key, $this->chunk );

        if ( isset( $this->project_metadata[ $chunk_key ] ) ) {
            return !!$this->project_metadata[ $chunk_key ];
        } else {
            if ( isset( $this->project_metadata[ $key ] ) ) {
                return !!$this->project_metadata[ $key ];
            } else {
                return false;
            }
        }
    }

}