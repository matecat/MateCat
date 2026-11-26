/* global process -- the bundler replaces process.env.ENABLE_NEW_EDITOR with the build flag */
// Which editor the cattool opens segments in. `ENABLE_NEW_EDITOR` comes from inc/config.ini at build time (see the
// `define` block of vite.config.js); this is the only place that reads it.

/**
 * True unless the build switches the CodeMirror editor off; on when the flag is missing. Every user gets the same
 * editor: setting the flag to false and rebuilding brings back the previous one, which stays in the bundle until it
 * is removed.
 */
export const isNewEditorEnabled = () => {
  const flag = process.env.ENABLE_NEW_EDITOR
  return flag !== false && flag !== 'false'
}
