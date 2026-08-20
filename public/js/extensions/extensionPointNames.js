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
