/* global process -- the bundler replaces process.env.ENABLE_NEW_EDITOR with the build flag */
// Which editor the cattool opens segments in. `ENABLE_NEW_EDITOR` comes from inc/config.ini at build time (see the
// `define` block of vite.config.js); this is the only place that reads it.

/** True unless the build switches the CodeMirror editor off. On when the flag is missing, as the build default. */
export const isNewEditorEnabled = () => {
  const flag = process.env.ENABLE_NEW_EDITOR
  return flag !== false && flag !== 'false'
}
