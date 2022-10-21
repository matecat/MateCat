<?php

/**
 * Created by PhpStorm.
 * @author ostico domenico@translated.net / ostico@gmail.com
 * Date: 13/06/19
 * Time: 12.35
 *
 */

namespace ProjectManager;

use ArrayObject;
use Database;
use Exception;
use Log;
use PDOException;
use Projects_ProjectDao;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;

class ProjectManagerModel {

    /**
     * Creates record in projects tabele and instantiates the project struct
     * internally.
     *
     * @param ArrayObject $projectStructure
     *
     * @return \Projects_ProjectStruct
     */
    public static function createProjectRecord( ArrayObject $projectStructure ) {

        $data                        = [];
        $data[ 'id' ]                = $projectStructure[ 'id_project' ];
        $data[ 'id_customer' ]       = $projectStructure[ 'id_customer' ];
        $data[ 'id_team' ]           = $projectStructure[ 'id_team' ];
        $data[ 'name' ]              = $projectStructure[ 'project_name' ];
        $data[ 'create_date' ]       = $projectStructure[ 'create_date' ];
        $data[ 'status_analysis' ]   = $projectStructure[ 'status' ];
        $data[ 'password' ]          = $projectStructure[ 'ppassword' ];
        $data[ 'pretranslate_100' ]  = $projectStructure[ 'pretranslate_100' ];
        $data[ 'remote_ip_address' ] = empty( $projectStructure[ 'user_ip' ] ) ? 'UNKNOWN' : $projectStructure[ 'user_ip' ];
        $data[ 'id_assignee' ]       = $projectStructure[ 'id_assignee' ];
        $data[ 'instance_id' ]       = !is_null( $projectStructure[ 'instance_id' ] ) ? $projectStructure[ 'instance_id' ] : null;
        $data[ 'due_date' ]          = !is_null( $projectStructure[ 'due_date' ] ) ? $projectStructure[ 'due_date' ] : null;

        $db = Database::obtain();
        $db->begin();
        $projectId = $db->insert( 'projects', $data );
        $project   = Projects_ProjectDao::findById( $projectId );
        $db->commit();

        return $project;

    }

    /**
     * @param ArrayObject $projectStructure
     * @param             $file_name
     * @param             $mime_type
     * @param             $fileDateSha1Path
     *
     * @return mixed|string
     * @throws Exception
     */
    public static function insertFile( ArrayObject $projectStructure, $file_name, $mime_type, $fileDateSha1Path, $meta = null ) {

        $data                         = [];
        $data[ 'id_project' ]         = $projectStructure[ 'id_project' ];
        $data[ 'filename' ]           = $file_name;
        $data[ 'source_language' ]    = $projectStructure[ 'source_language' ];
        $data[ 'mime_type' ]          = $mime_type;
        $data[ 'sha1_original_file' ] = $fileDateSha1Path;
        $data[ 'is_converted' ]       = isset($meta['mustBeConverted']) ? $meta['mustBeConverted'] : null;

        $db = Database::obtain();

        try {
            $idFile = $db->insert( 'files', $data );
        } catch ( PDOException $e ) {
            Log::doJsonLog( "Database insert error: {$e->getMessage()} " );
            throw new Exception( "Database insert file error: {$e->getMessage()} ", -$e->getCode() );
        }

        return $idFile;

    }

    public static function insertPreTranslations( &$query_translations_values ) {

        $dbHandler = Database::obtain();

        $baseQuery = "
                INSERT INTO segment_translations (
                        id_segment, 
                        id_job, 
                        segment_hash, 
                        status, 
                        translation, 
                        translation_date, /* NOW() */
                        tm_analysis_status, /* DONE */
                        locked, 
                        match_type, 
                        eq_word_count,
                        serialized_errors_list,
                        warning,
                        suggestion_match,
                        standard_word_count
                )
                VALUES ";

        $tuple_marks = "( ?, ?, ?, ?, ?, NOW(), 'DONE', ?, ?, ?, ?, ?, ?, ? )";

        Log::doJsonLog( "Pre-Translations: Total Rows to insert: " . count( $query_translations_values ) );

        //split the query in to chunks if there are too much segments
        $query_translations_values = array_chunk( $query_translations_values, 100 );

        Log::doJsonLog( "Pre-Translations: Total Queries to execute: " . count( $query_translations_values ) );

        foreach ( $query_translations_values as $i => $chunk ) {

            try {

                $query = $baseQuery . rtrim( str_repeat( $tuple_marks . ", ", count( $chunk ) ), ", " );
                $stmt  = $dbHandler->getConnection()->prepare( $query );
                $stmt->execute( iterator_to_array( new RecursiveIteratorIterator( new RecursiveArrayIterator( $chunk ) ), false ) );

                Log::doJsonLog( "Pre-Translations: Executed Query " . ( $i + 1 ) );
            } catch ( PDOException $e ) {
                Log::doJsonLog( "Segment import - DB Error: " . $e->getMessage() . " - \n" );
                throw new PDOException( "Translations Segment import - DB Error: " . $e->getMessage() . " - $chunk", -2 );
            }

        }

    }

    /**
     * @param $notes
     *
     * @throws Exception
     */
    public static function bulkInsertSegmentNotes( $notes ) {
        $template = " INSERT INTO segment_notes ( id_segment, internal_id, note, json ) VALUES ";

        $insert_values = [];
        $chunk_size    = 30;

        foreach ( $notes as $internal_id => $v ) {

            $attributes = $v[ 'from' ];
            $entries  = $v[ 'entries' ];
            $segments = $v[ 'segment_ids' ];

            $json_entries     = $v[ 'json' ];
            $json_segment_ids = $v[ 'json_segment_ids' ];

            foreach ( $segments as $id_segment ) {
                foreach ( $entries as $index => $note ) {

                    // NOTE
                    // we need to strip tags from $note
                    // to prevent possible xss attacks
                    // from the UI

                    if(isset($attributes['entries'][$index])) {
                        $metaKey = strip_tags( html_entity_decode( $attributes[ 'entries' ][ $index ] ) );

                        // check for metaKey is `notes`
                        if($metaKey === 'notes' or $metaKey === 'NO_FROM'){
                            $insert_values[] = [ $id_segment, $internal_id, strip_tags(html_entity_decode($note)), null ];
                        }

                    } else {
                        $insert_values[] = [ $id_segment, $internal_id, strip_tags(html_entity_decode($note)), null ];
                    }
                }
            }

            foreach ( $json_segment_ids as $id_segment ) {
                foreach ( $json_entries as $index => $json ) {

                    if(isset($attributes['json'][$index])) {
                        $metaKey = $attributes['json'][$index];

                        if($metaKey === 'notes' or $metaKey === 'NO_FROM'){
                            $insert_values[] = [ $id_segment, $internal_id, null, $json ];
                        }

                    } else {
                        $insert_values[] = [ $id_segment, $internal_id, null, $json ];
                    }
                }
            }

        }

        $chunked = array_chunk( $insert_values, $chunk_size );
        Log::doJsonLog( "Notes: Total Rows to insert: " . count( $chunked ) );

        $conn = Database::obtain()->getConnection();

        try {

            foreach ( $chunked as $i => $chunk ) {
                $values_sql_array = array_fill( 0, count( $chunk ), " ( ?, ?, ?, ? ) " );
                $stmt             = $conn->prepare( $template . implode( ', ', $values_sql_array ) );
                $flattened_values = array_reduce( $chunk, 'array_merge', [] );
                $stmt->execute( $flattened_values );
                Log::doJsonLog( "Notes: Executed Query " . ( $i + 1 ) );
            }

        } catch ( Exception $e ) {
            Log::doJsonLog( "Notes import - DB Error: " . $e->getMessage() );
            /** @noinspection PhpUndefinedVariableInspection */
            Log::doJsonLog( "Notes import - Statement: " . $stmt->queryString );
            Log::doJsonLog( "Notes Chunk Dump: " . var_export( $chunk, true ) );
            /** @noinspection PhpUndefinedVariableInspection */
            Log::doJsonLog( "Notes Flattened Values Dump: " . var_export( $flattened_values, true ) );
            throw new Exception( "Notes import - DB Error: " . $e->getMessage(), 0, $e );
        }

    }

    /**
     * @param $notes
     *
     * @throws Exception
     */
    public static function bulkInsertSegmentMetaDataFromAttributes ( $notes ) {

        $template = " INSERT INTO segment_metadata ( id_segment, meta_key, meta_value ) VALUES ";

        $insert_values = [];
        $chunk_size    = 30;

        foreach ( $notes as $internal_id => $v ) {

            $attributes = $v[ 'from' ];
            $entries  = $v[ 'entries' ];
            $segments = $v[ 'segment_ids' ];

            $json_entries     = $v[ 'json' ];
            $json_segment_ids = $v[ 'json_segment_ids' ];

            foreach ( $segments as $id_segment ) {
                foreach ( $entries as $index => $note ) {

                    if(isset($attributes['entries'][$index])){
                        $metaKey = strip_tags(html_entity_decode($attributes['entries'][$index]));
                        $metaValue = strip_tags(html_entity_decode($note));

                        if($metaKey !== 'notes' and $metaKey !== 'NO_FROM'){
                            $insert_values[] = [ $id_segment, $metaKey, $metaValue ];
                        }
                    }
                }
            }

            foreach ( $json_segment_ids as $id_segment ) {
                foreach ( $json_entries as $index => $json ) {

                    if(isset($attributes['json'][$index])){
                        $metaKey = $attributes['json'][$index];
                        $metaValue = $json;

                        if($metaKey !== 'notes' and $metaKey !== 'NO_FROM'){
                            $insert_values[] = [ $id_segment, $metaKey, $metaValue ];
                        }
                    }
                }
            }
        }

        $chunked = array_chunk( $insert_values, $chunk_size );
        Log::doJsonLog( "Notes attributes: Total Rows to insert: " . count( $chunked ) );

        $conn = Database::obtain()->getConnection();

        try {

            foreach ( $chunked as $i => $chunk ) {
                $values_sql_array = array_fill( 0, count( $chunk ), " ( ?, ?, ? ) " );
                $stmt             = $conn->prepare( $template . implode( ', ', $values_sql_array ) );
                $flattened_values = array_reduce( $chunk, 'array_merge', [] );
                $stmt->execute( $flattened_values );
                Log::doJsonLog( "Notes attributes: Executed Query " . ( $i + 1 ) );
            }

        } catch ( Exception $e ) {
            Log::doJsonLog( "Notes attributes import - DB Error: " . $e->getMessage() );
            /** @noinspection PhpUndefinedVariableInspection */
            Log::doJsonLog( "Notes attributes import - Statement: " . $stmt->queryString );
            Log::doJsonLog( "Notes attributes Chunk Dump: " . var_export( $chunk, true ) );
            /** @noinspection PhpUndefinedVariableInspection */
            Log::doJsonLog( "Notes attributes Flattened Values Dump: " . var_export( $flattened_values, true ) );
            throw new Exception( "Notes attributes import - DB Error: " . $e->getMessage(), 0, $e );
        }
    }

    /**
     * @param $projectStructure
     *
     * @throws Exception
     */
    public static function bulkInsertContextsGroups( $projectStructure ) {

        $template = " INSERT INTO context_groups ( id_project, id_segment, context_json ) VALUES ";

        $insert_values = [];
        $chunk_size    = 30;

        $id_project = $projectStructure[ 'id_project' ];

        foreach ( $projectStructure[ 'context-group' ] as $internal_id => $v ) {

            $context_json = json_encode( $v[ 'context_json' ] );
            $segments     = $v[ 'context_json_segment_ids' ];

            foreach ( $segments as $id_segment ) {
                $insert_values[] = [ $id_project, $id_segment, $context_json ];
            }

        }

        $chunked = array_chunk( $insert_values, $chunk_size );
        Log::doJsonLog( "Notes: Total Rows to insert: " . count( $chunked ) );

        $conn = Database::obtain()->getConnection();

        try {

            foreach ( $chunked as $i => $chunk ) {
                $values_sql_array = array_fill( 0, count( $chunk ), " ( ?, ?, ? ) " );
                $stmt             = $conn->prepare( $template . implode( ', ', $values_sql_array ) );
                $flattened_values = array_reduce( $chunk, 'array_merge', [] );
                $stmt->execute( $flattened_values );
                Log::doJsonLog( "Notes: Executed Query " . ( $i + 1 ) );
            }

        } catch ( Exception $e ) {
            Log::doJsonLog( "Trans-Unit Context Groups import - DB Error: " . $e->getMessage() );
            /** @noinspection PhpUndefinedVariableInspection */
            Log::doJsonLog( "Trans-Unit Context Groups import - Statement: " . $stmt->queryString );
            Log::doJsonLog( "Trans-Unit Context Groups Chunk Dump: " . var_export( $chunk, true ) );
            /** @noinspection PhpUndefinedVariableInspection */
            Log::doJsonLog( "Trans-Unit Context Groups Flattened Values Dump: " . var_export( $flattened_values, true ) );
            throw new Exception( "Notes import - DB Error: " . $e->getMessage(), 0, $e );
        }

    }

}