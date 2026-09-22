const nodeEcmaVersion = 2018
const browserEcmaVersion = 2018

const babelParserOptions = (ecmaVersion) => ({
  sourceType: 'module',
  ecmaVersion,
  ecmaFeatures: {jsx: true},
  requireConfigFile: false,
  babelOptions: {
    configFile: false,
    babelrc: false,
    presets: ['@babel/preset-react'],
  },
})

// Core is public; the deployments that customise it are not. A name from one of
// them in core code is a leak, and it is also a design smell: the difference
// belongs on one of core's helper objects, for the deployment to replace.
//
// The names cannot be committed, so the list lives in a gitignored file that each
// checkout and CI supplies. See .eslint-private-names.example.json. Without that
// file the rule is absent and everything else still lints.
const privateNames = (() => {
  try {
    // eslint-disable-next-line no-undef
    const {names} = require('./.eslint-private-names.json')
    return Array.isArray(names) ? names : []
  } catch (e) {
    return []
  }
})()

const escapeForRegExp = (value) => value.replace(/[.*+?^${}()|[\]\\-]/g, '\\$&')

const privateNameRules = privateNames.map((name) => {
  const pattern = `/${escapeForRegExp(name)}/i`
  return {
    selector: [
      `Literal[value=${pattern}]`,
      `TemplateElement[value.raw=${pattern}]`,
      `Identifier[name=${pattern}]`,
      `JSXIdentifier[name=${pattern}]`,
    ].join(', '),
    message:
      'Core must not name a particular deployment. Put the difference on a ' +
      'helper object and let the deployment replace that member.',
  }
})

module.exports = {
  ignorePatterns: [
    '**/public/js/lib/**/*.js',
    // Bundler output and vendored bundles, not source.
    '**/plugins/*/app/build/**/*.js',
    'public/api/dist/**/*.js',
  ],
  // Everything in the tree is modern JS. Without a parser here the default one
  // reads any file outside the globs below as ES5 and reports `import` as a
  // syntax error, which suppresses every other rule in that file.
  parser: '@babel/eslint-parser',
  parserOptions: babelParserOptions(browserEcmaVersion),
  extends: ['eslint:recommended'],
  rules: {
    'no-extra-semi': 'off',
    'no-undef': 'warn',
  },
  overrides: [
    // Node-side files: build and tooling config plus the socket server. They
    // are not browser code and need `module`, `require` and `process`.
    {
      files: [
        '*.config.js',
        'babel.utils.js',
        'check-circular-deps.js',
        'jest.polyfills.js',
        'nodejs/**/*.js',
      ],
      env: {node: true, es6: true},
    },

    // Browser source sitting outside the `js/` directories the overrides below
    // match: the icon components, the Vite entry points and the plugin sources.
    // React's rules come along because without `react/jsx-uses-react` every one
    // of these files reports its own `React` import as an unused variable.
    {
      files: [
        'public/img/**/*.js',
        'public/api/**/*.js',
        'public/vite-entries/**/*.js',
        '**/plugins/*/static/src/**/*.js',
        '**/plugins/*/app/src/**/*.js',
      ],
      env: {browser: true, es6: true},
      // `globalThis` is ES2020; the parser is pinned to 2018 above.
      globals: {globalThis: 'readonly'},
      extends: ['plugin:react/recommended', 'plugin:react-hooks/recommended'],
      settings: {react: {version: '16.9'}},
      rules: {
        'react/prop-types': 'off',
        // @vitejs/plugin-react builds these with the automatic JSX runtime, so
        // the React import is optional. Both forms appear here and neither is
        // wrong; leaving the rule on would report the files that omit it.
        'react/react-in-jsx-scope': 'off',
      },
    },

    // jest related files
    {
      files: ['**/*.jest.js', '**/*.test.js', '**/mocks/**/*.js'],
      parser: '@babel/eslint-parser',
      parserOptions: babelParserOptions(nodeEcmaVersion),
      env: {jest: true, node: true, browser: true, es6: true},
      extends: [
        'plugin:jest/recommended',
        'plugin:jest-dom/recommended',
        'plugin:testing-library/react',
      ],
    },

    // grunt browserify compiled files
    {
      files: ['**/js/**/*.js'],
      parser: '@babel/eslint-parser',
      env: {es6: true},
      parserOptions: babelParserOptions(browserEcmaVersion),
      extends: ['plugin:react/recommended', 'plugin:react-hooks/recommended'],
      settings: {
        react: {version: '16.9'},
      },
      rules: {
        'react/prop-types': 'off',
        // The class form is gone from the tree; keep it out. Until the
        // migration finished this was a per-directory allowlist that grew
        // one PR at a time.
        'no-restricted-syntax': [
          'error',
          {
            selector:
              'ClassDeclaration[superClass.name=/^(Pure)?Component$/], ' +
              'ClassDeclaration[superClass.property.name=/^(Pure)?Component$/]',
            message: 'Write function components with hooks.',
          },
          ...privateNameRules,
        ],
      },
    },

    // grunt concat related files
    {
      files: ['**/public/js/**/*.js'],
      env: {browser: true},
      parserOptions: {ecmaVersion: browserEcmaVersion},
      // Put on the page by the server template or a third-party script tag,
      // never imported. Names that only look global because an import is
      // missing are left alone, so they keep reporting.
      globals: {
        config: 'readonly',
        globalFunctions: 'readonly',
        google: 'readonly',
        gapi: 'readonly',
      },
    },
  ],
}
