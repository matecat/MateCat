<?php

namespace Model\ProjectCreation;

/**
 * Error codes used during project creation.
 *
 * These integer values flow through Redis to the frontend via
 * {@see \Utils\ActiveMQ\ClientHelpers\ProjectQueue::publishResults()} and
 * {@see \Controller\API\App\ProjectCreationStatusController}, so their
 * numeric values are part of the public contract and must not change
 * without coordinating with the frontend.
 *
 * Cases are ordered numerically from -1 descending.
 *
 * @see ProjectManager::addProjectError()
 * @see ProjectManager::mapFileInsertionError()
 * @see ProjectManager::mapSegmentExtractionError()
 */
enum ProjectCreationError: int
{
    /** No translatable text or segments in the file */
    case NO_TRANSLATABLE_TEXT = -1;

    /** Failed to find converted XLIFF (null path or invalid extension) */
    case XLIFF_NOT_FOUND = -3;

    /** XLIFF parse failure in SegmentExtractor */
    case XLIFF_PARSE_FAILURE = -4;

    /** TM key validation failure */
    case TM_KEY_INVALID = -5;

    /** File isn't found or not saved (covers multiple throw sites) */
    case FILE_NOT_FOUND = -6;

    /** XLIFF import error (remapped from XLIFF_PARSE_FAILURE in output) */
    case XLIFF_IMPORT_ERROR = -7;

    /** Failed to store/link an original zip file */
    case ZIP_STORE_FAILED = -10;

    /** Failed to store reference files on disk (permission denied) */
    case REFERENCE_FILES_DISK_ERROR = -11;

    /** Failed to store reference files in the database */
    case REFERENCE_FILES_DB_ERROR = -12;

    /** File cache/copy error (origin outside the expected directory) */
    case FILE_CACHE_ERROR = -13;

    /** TMX import timed out during MyMemory polling */
    case TMX_IMPORT_TIMEOUT = -15;

    /** XLIFF conversion wasn't found on disk (remapped from XLIFF_NOT_FOUND in output) */
    case XLIFF_CONVERSION_NOT_FOUND = -16;

    /** Invalid or missing upload token */
    case INVALID_UPLOAD_TOKEN = -19;

    /** Segment has too many notes (exceeds SEGMENT_NOTES_LIMIT) */
    case TOO_MANY_NOTES = -44;

    /** Notes bulk insert DB error */
    case BULK_INSERT_NOTES = -101;

    /** Pre-translations bulk insert DB error */
    case BULK_INSERT_PRE_TRANSLATIONS = -102;

    /** Segment metadata bulk insert DB error */
    case BULK_INSERT_SEGMENT_METADATA = -103;

    /** Context groups bulk insert DB error */
    case BULK_INSERT_CONTEXT_GROUPS = -104;

    /** Files couldn't move from cache to final dir, or S3 encoding error */
    case FILE_MOVE_FAILED = -200;

    /** Failed to compute file hash (sha1) or cache package error */
    case FILE_HASH_FAILED = -230;

    // ── Non-negative codes ─────────────────────────────────────────

    /** Generic or unset error code */
    case GENERIC_ERROR = 0;

    /** Source word count exceeds MAX_SOURCE_WORDS */
    case MAX_WORDS_EXCEEDED = 128;

    /** Invalid XLIFF parameters (DomainException) */
    case INVALID_XLIFF_PARAMETERS = 400;
}
