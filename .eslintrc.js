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
      files: ['**/*.jest.js', '**/*.test.js', '**/mocks/**/*.js'],
      parser: '@babel/eslint-parser',
      parserOptions: {
        sourceType: 'module',
        ecmaVersion: nodeEcmaVersion,
        ecmaFeatures: {jsx: true},
      },
      env: {jest: true, node: true, browser: true, es6: true},
      extends: [
        'plugin:jest/recommended',
        'plugin:jest-dom/recommended',
        'plugin:testing-library/react',
      ],
    },

    // grunt browserify compiled files
    {
      files: ['**/cat_source/**/*.js', '**/js/*.js'],
      parser: '@babel/eslint-parser',
      env: {es6: true},
      parserOptions: {
        sourceType: 'module',
        ecmaVersion: browserEcmaVersion,
        ecmaFeatures: {jsx: true},
      },
      extends: ['plugin:react/recommended', 'plugin:react-hooks/recommended'],
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
    },
  ],
}
