// The names of the extension points, and nothing else.
//
// Deliberately free of imports: both the manifest that defines these points and
// the call sites that consume them import this module, and a shared leaf keeps
// that from becoming an import cycle through whichever module holds a default.

export const SEGMENT_CONTEXT_BEFORE = 'segment.contextBefore'
export const SEGMENT_CONTEXT_AFTER = 'segment.contextAfter'
export const SEGMENT_ID_BEFORE = 'segment.idBefore'
export const SEGMENT_ID_AFTER = 'segment.idAfter'
export const SEGMENT_FOOTER_TABS = 'segment.footerTabs'
export const SEGMENT_IS_ICE = 'segment.isIce'
export const SEGMENT_HAS_NOTE = 'segment.hasNote'
export const SEGMENT_FILE_ID = 'segment.fileId'
export const FILES_PARSE = 'files.parse'
export const FILE_HAS_INSTRUCTIONS = 'file.hasInstructions'
export const LINK_ALLOWED_REDIRECT = 'link.allowedRedirect'
// One point, two consumers: the CAT tool page and the project-template hook both
// need the same preset, and only one preset can be in force at a time.
export const CHARS_COUNTER_MODE = 'charsCounter.mode'
