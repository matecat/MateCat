import path from 'node:path'
import {fileURLToPath} from 'node:url'
import {defineConfig} from 'vite'

const here = path.dirname(fileURLToPath(import.meta.url))

// Serves the harness page only: no app config, no plugins.
export default defineConfig({
  root: here,
  logLevel: 'warn',
  server: {fs: {allow: [path.resolve(here, '../../../../..')]}},
  // the cattool tooltip stylesheet (harness.js) resolves its `@use` from the sass root, as the app build does
  css: {
    preprocessorOptions: {
      scss: {loadPaths: [path.resolve(here, '../../../../css/sass')]},
    },
  },
})
