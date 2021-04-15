const nodeEcmaVersion = 2018
const browserEcmaVersion = 2018

module.exports = {
  ignorePatterns: ['**/public/js/lib/**/*.js'],
  extends: ['eslint:recommended'],
  rules: {
    'no-extra-semi': 'off',
    'no-undef': 'warn',
  },
  overrides: [
    // nodejs 9.11 related files
    {
      files: ['*.js', '**/support_scripts/**/*.js'],
      parserOptions: {
        ecmaVersion: nodeEcmaVersion,
      },
      env: {node: true},
    },

    // jest related files
    {
      files: ['**/*.jest.js', '**/*.test.js'],
      parserOptions: {
        sourceType: 'module',
        ecmaVersion: nodeEcmaVersion,
        ecmaFeatures: {jsx: true},
      },
      env: {jest: true, node: true, browser: true},
    },

    // grunt browserify compiled files
    {
      files: ['**/cat_source/es6/**/*.js'],
      parser: '@babel/eslint-parser',
      parserOptions: {
        sourceType: 'module',
        ecmaVersion: browserEcmaVersion,
        ecmaFeatures: {jsx: true},
      },
      extends: [
        'plugin:import/errors',
        'plugin:import/warnings',
        'plugin:react/recommended',
      ],
      settings: {
        react: {version: '16.9'},
      },
      rules: {'react/prop-types': 'off'},
    },

    // grunt concat related files
    {
      files: ['**/public/js/**/*.js'],
      env: {browser: true},
      parserOptions: {ecmaVersion: browserEcmaVersion},

      rules: {
        strict: 'error',
      },
    },
  ],
}
