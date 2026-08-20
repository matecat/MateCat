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
  SEGMENT_CONTEXT_AFTER,
  SEGMENT_CONTEXT_BEFORE,
  SEGMENT_FOOTER_TABS,
  SEGMENT_ID_AFTER,
  SEGMENT_ID_BEFORE,
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
