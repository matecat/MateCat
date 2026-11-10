/* global process -- the bundler replaces process.env.ENABLE_NEW_EDITOR with the build flag */
// Which editor the cattool opens segments in. `ENABLE_NEW_EDITOR` comes from inc/config.ini at build time (see the
// `define` block of vite.config.js); this is the only place that reads it.

/** True unless the build switches the CodeMirror editor off. On when the flag is missing, as the build default. */
export const isNewEditorEnabled = () => {
  const flag = process.env.ENABLE_NEW_EDITOR
  return flag !== false && flag !== 'false'
}

/** The user metadata key holding the editor the user picked in the Editor settings tab: 'cm6' or 'draft'. */
export const EDITOR_ENGINE_KEY = 'editor_engine'

/**
 * True when a segment opens in the CodeMirror editor: the build allows it and the user has not switched back to the
 * previous editor. A user who never picked one gets the new editor. The single place that decides it, for the source
 * now and for the target once it moves too.
 */
export const isNewEditorActive = (metadata) =>
  isNewEditorEnabled() && metadata?.[EDITOR_ENGINE_KEY] !== 'draft'
