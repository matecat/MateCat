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
    // jest related files
    {
      files: ['**/*.jest.js', '**/*.test.js', '**/mocks/**/*.js'],
      parser: '@babel/eslint-parser',
      parserOptions: {
        sourceType: 'module',
        ecmaVersion: nodeEcmaVersion,
        ecmaFeatures: {jsx: true},
        requireConfigFile: false,
        babelOptions: {
          configFile: false,
          babelrc: false,
          presets: ['@babel/preset-react'],
        },
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
      files: ['**/js/**/*.js'],
      parser: '@babel/eslint-parser',
      env: {es6: true},
      parserOptions: {
        sourceType: 'module',
        ecmaVersion: browserEcmaVersion,
        ecmaFeatures: {jsx: true},
        requireConfigFile: false,
        babelOptions: {
          configFile: false,
          babelrc: false,
          presets: ['@babel/preset-react'],
        },
      },
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
          'warn',
          {
            selector:
              'ClassDeclaration[superClass.name=/^(Pure)?Component$/], ' +
              'ClassDeclaration[superClass.property.name=/^(Pure)?Component$/]',
            message: 'Write function components with hooks.',
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
