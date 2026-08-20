// The extension contract, in one place.
//
// Every name below is a point a deployment may replace, together with what core
// does when nothing replaces it. This file is the whole reviewable API: adding a
// point means adding a line here, and registering against a name that is not
// here throws at boot rather than doing nothing.
//
// Names are capabilities, never a description of who overrides them.

import {defineExtensionPoint} from './extensionPoints'
import {
  FILE_HAS_INSTRUCTIONS,
  FILES_PARSE,
  LINK_ALLOWED_REDIRECT,
  SEGMENT_CONTEXT_AFTER,
  SEGMENT_CONTEXT_BEFORE,
  SEGMENT_FILE_ID,
  SEGMENT_FOOTER_TABS,
  SEGMENT_HAS_NOTE,
  SEGMENT_ID_AFTER,
  SEGMENT_ID_BEFORE,
  SEGMENT_IS_ICE,
} from './extensionPointNames'
import {
  getContextAfter,
  getContextBefore,
  getIdAfter,
  getIdBefore,
  registerFooterTabs,
} from './segmentEditorDefaults'

defineExtensionPoint(SEGMENT_CONTEXT_BEFORE, getContextBefore)
defineExtensionPoint(SEGMENT_CONTEXT_AFTER, getContextAfter)
defineExtensionPoint(SEGMENT_ID_BEFORE, getIdBefore)
defineExtensionPoint(SEGMENT_ID_AFTER, getIdAfter)
defineExtensionPoint(SEGMENT_FOOTER_TABS, registerFooterTabs)

// Small enough to live here. Anything with a real body belongs in the module that
// owns the domain and gets imported by reference, like the five points above.
defineExtensionPoint(SEGMENT_IS_ICE, (segment) => segment.ice_locked)
defineExtensionPoint(SEGMENT_HAS_NOTE, (segment) =>
  Boolean(
    segment.notes ||
    segment.context_groups?.context_json ||
    segment.metadata?.length > 0,
  ),
)
defineExtensionPoint(SEGMENT_FILE_ID, (segment) => segment.id_file)
defineExtensionPoint(FILES_PARSE, (files) => files)
defineExtensionPoint(
  FILE_HAS_INSTRUCTIONS,
  (file) => file && file.metadata && file.metadata.instructions,
)
defineExtensionPoint(LINK_ALLOWED_REDIRECT, () => false)
