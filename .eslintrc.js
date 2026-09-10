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
  ignorePatterns: ['**/public/js/lib/**/*.js'],
  extends: ['eslint:recommended'],
  rules: {
    'no-extra-semi': 'off',
    'no-undef': 'warn',
  },
  overrides: [
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
        // Catches every class component, not just the trivially-stateless ones
        // react/prefer-stateless-function reports. The superClass matchers are
        // anchored so `extends SomeInterface` stubs are not swept up.
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

    // Plugin front-end sources. These files previously failed to parse at all
    // under `yarn lint`, so nothing in them was ever checked. They replace a
    // member of one of core's own helper objects, which is why patching a
    // prototype is fenced off: a function component has none to patch.
    {
      files: ['plugins/*/static/**/*.js'],
      parser: '@babel/eslint-parser',
      env: {browser: true, es6: true},
      parserOptions: babelParserOptions(browserEcmaVersion),
      extends: ['plugin:react/recommended'],
      settings: {
        react: {version: '16.9'},
      },
      rules: {
        'react/prop-types': 'off',
        'no-restricted-syntax': [
          'error',
          {
            selector:
              "AssignmentExpression[left.object.property.name='prototype'], " +
              "AssignmentExpression[left.property.name='prototype']",
            message:
              'Assign over the member of the exported object instead. A ' +
              'function component has no prototype to patch.',
          },
        ],
      },
    },

    // grunt concat related files
    {
      files: ['**/public/js/**/*.js'],
      env: {browser: true},
      parserOptions: {ecmaVersion: browserEcmaVersion},
    },
  ],
}
