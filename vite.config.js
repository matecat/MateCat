import {defineConfig, transformWithOxc} from 'vite'
import react from '@vitejs/plugin-react'
import {readFileSync, writeFileSync, existsSync} from 'node:fs'
import {resolve, join} from 'node:path'
import {createRequire} from 'node:module'
import {globSync} from 'glob'

const require = createRequire(import.meta.url)
const ini = require('ini')

const matecatConfig = ini.parse(readFileSync('./inc/config.ini', 'utf-8'))

// Single source of truth: template name → Vite entry names
const entryGroups = JSON.parse(readFileSync('./public/vite-entries/groups.json', 'utf-8'))

const allEntryNames = [...new Set(Object.values(entryGroups).flat())]
const input = Object.fromEntries(
  allEntryNames.map((name) => [name, `public/vite-entries/${name}.js`]),
)

// Load plugin-specific build configs (same pattern as webpack.config.js).
// Each plugin can provide a plugin.vite.config.js that assigns to pluginViteConfig.
const pluginConfigFiles = globSync('./plugins/*/plugin.vite.config.js', {
  ignore: './plugins/**/node_modules/**',
})
let pluginConfig = {}
for (const file of pluginConfigFiles) {
  const data = readFileSync(file, 'utf-8')
  const config = (0, eval)(data)
  pluginConfig = {...pluginConfig, ...config}
}

let sentryVitePlugin = null
if (pluginConfig.sentryVitePlugin) {
  try {
    sentryVitePlugin = require('@sentry/vite-plugin').sentryVitePlugin
  } catch {
    // @sentry/vite-plugin not installed — open-source repo without Sentry
  }
}

// Vite 8 OXC fix: .js files default to lang:'js' (no JSX parsing).
// Pre-transform with lang:'jsx' so builtin:vite-transform doesn't choke.
function jsxInJsPlugin() {
  return {
    name: 'transform-js-as-jsx',
    enforce: 'pre',
    async transform(code, id) {
      if (/\.js$/.test(id) && !id.includes('node_modules')) {
        return transformWithOxc(code, id, {lang: 'jsx'})
      }
    },
  }
}

/**
 * Post-build plugin: reads source PHPTAL templates from lib/View/templates/,
 * injects Vite asset tags (with TAL nonce attributes), and writes complete
 * output templates to lib/View/ — the same location Webpack writes to.
 *
 * PHP serves the output templates directly. Zero manifest parsing at runtime.
 * Tags use tal:attributes="nonce x_nonce_unique_id" so PHPTAL injects the
 * per-request CSP nonce at render time.
 */
function htmlTemplatePlugin() {
  const NONCE_ATTR = 'tal:attributes="nonce x_nonce_unique_id"'
  const BASE = '/public/build/'
  const TEMPLATES_DIR = resolve('lib/View/templates')
  const OUTPUT_DIR = resolve('lib/View')

  function collectDeps(manifest, chunkKey, seen, preloads, styles) {
    if (seen.has(chunkKey)) return
    seen.add(chunkKey)

    const chunk = manifest[chunkKey]
    if (!chunk) return

    for (const importKey of chunk.imports ?? []) {
      collectDeps(manifest, importKey, seen, preloads, styles)
    }

    if (!chunk.isEntry && chunk.file) {
      preloads.push(chunk.file)
    }

    for (const css of chunk.css ?? []) {
      styles.push(css)
    }
  }

  return {
    name: 'html-templates',
    apply: 'build',
    closeBundle() {
      const outDir = resolve('public/build')
      const manifestPath = join(outDir, '.vite', 'manifest.json')

      if (!existsSync(manifestPath)) {
        console.warn('[html-templates] No manifest found, skipping.')
        return
      }

      const manifest = JSON.parse(readFileSync(manifestPath, 'utf-8'))

      for (const [templateName, entries] of Object.entries(entryGroups)) {
        const sourcePath = join(TEMPLATES_DIR, `_${templateName}`)
        if (!existsSync(sourcePath)) {
          console.warn(
            `[html-templates] Source template "_${templateName}" not found, skipping.`,
          )
          continue
        }

        let html = readFileSync(sourcePath, 'utf-8')
        const outputPath = join(OUTPUT_DIR, templateName)

        // Empty entries — just copy template without injection
        if (entries.length === 0) {
          writeFileSync(outputPath, html)
          console.log(`[html-templates] _${templateName} → ${templateName} (copy)`)
          continue
        }

        const scripts = []
        const styles = []
        const preloads = []
        const seen = new Set()

        for (const entryName of entries) {
          const key = `public/vite-entries/${entryName}.js`
          const entry = manifest[key]
          if (!entry) {
            console.warn(`[html-templates] Entry "${key}" not in manifest.`)
            continue
          }

          for (const importKey of entry.imports ?? []) {
            collectDeps(manifest, importKey, seen, preloads, styles)
          }

          if (entry.file) {
            scripts.push(entry.file)
          }

          for (const css of entry.css ?? []) {
            styles.push(css)
          }
        }

        // .php templates use their own nonce mechanism — inject plain tags
        const isPhp = templateName.endsWith('.php')
        const nonce = isPhp ? '' : ` ${NONCE_ATTR}`

        const tags = []

        for (const file of [...new Set(preloads)]) {
          tags.push(
            `    <link rel="modulepreload" href="${BASE}${file}"${nonce}/>`,
          )
        }

        for (const file of [...new Set(styles)]) {
          tags.push(
            `    <link rel="stylesheet" href="${BASE}${file}"${nonce}/>`,
          )
        }

        for (const file of scripts) {
          tags.push(
            `    <script type="module" src="${BASE}${file}"${nonce}></script>`,
          )
        }

        const injection =
          `    <!-- Vite assets (auto-generated) -->\n${tags.join('\n')}\n`
        html = html.replace('</body>', injection + '</body>')

        writeFileSync(outputPath, html)
        console.log(
          `[html-templates] _${templateName} → ${templateName} (${tags.length} tags)`,
        )
      }
    },
  }
}

const cliHttpHost = matecatConfig.CLI_HTTP_HOST?.replace(/"/g, '') || 'https://dev.matecat.com'
const hostUrl = new URL(cliHttpHost)

export default defineConfig(({mode, command}) => {
  const isProd = mode === 'production'
  const hasSentry = isProd && sentryVitePlugin && pluginConfig.sentryVitePlugin

  if (hasSentry && matecatConfig.BUILD_NUMBER) {
    pluginConfig.sentryVitePlugin.release = {
      name: matecatConfig.BUILD_NUMBER,
    }
    console.log('[sentry] release', pluginConfig.sentryVitePlugin.release)
  }

  return {
  plugins: [
    jsxInJsPlugin(),
    react({
      include: /\.(js|jsx)$/,
    }),
    htmlTemplatePlugin(),
    hasSentry && sentryVitePlugin(pluginConfig.sentryVitePlugin),
  ],

  define: {
    'process.env._ENV': JSON.stringify(matecatConfig.ENV ?? 'development'),
    'process.env.version': JSON.stringify(matecatConfig.BUILD_NUMBER ?? ''),
    'process.env.MODE': JSON.stringify(mode),
    global: 'globalThis',
  },

  resolve: {
    extensions: ['.js', '.jsx', '.json'],
  },

  base: command === 'serve' ? '/' : '/public/build/',
  publicDir: false,

  build: {
    manifest: true,
    outDir: 'public/build',
    emptyOutDir: true,
    sourcemap: hasSentry ? 'hidden' : mode !== 'production',

    rolldownOptions: {
      input,
      output: {
        entryFileNames: '[name].[hash].js',
        chunkFileNames: '[name].[hash].js',
        assetFileNames: 'assets/[name].[hash].[ext]',
        codeSplitting: {
          groups: [
            {
              name: 'react-vendor',
              test: /node_modules[\\/](react|react-dom|scheduler)/,
              priority: 20,
            },
            {
              name: 'editor-vendor',
              test: /node_modules[\\/](draft-js|immutable)/,
              priority: 15,
            },
            {
              name: 'vendor',
              test: /node_modules/,
              priority: 10,
            },
          ],
        },
      },
    },
  },

  optimizeDeps: {
    rolldownOptions: {
      moduleTypes: {'.js': 'jsx'},
    },
  },

  css: {
    devSourcemap: true,
    lightningcss: {
      errorRecovery: true,
    },
    preprocessorOptions: {
      scss: {
        quietDeps: true,
      },
    },
  },

  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    origin: cliHttpHost,
    cors: true,
    hmr: {
      protocol: hostUrl.protocol === 'https:' ? 'wss' : 'ws',
      host: hostUrl.hostname,
      clientPort: parseInt(hostUrl.port) || (hostUrl.protocol === 'https:' ? 443 : 80),
      path: '__vite_hmr',
    },
    watch: {
      usePolling: true,
      interval: 500,
      ignored: [
        '**/storage/**',
        '**/node_modules/**',
        '**/vendor/**',
        '**/public/build/**',
      ],
    },
  },
}})
