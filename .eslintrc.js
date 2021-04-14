const nodeEcmaVersion = 2020

module.exports = {
  overrides: [
    {
      files: ['*.js', './support_scripts/**/*.js'],
      parserOptions: {
        ecmaVersion: nodeEcmaVersion,
      },
      env: {node: true},
    },
    {
      files: ['**/*.jest.js'],
      parserOptions: {
        sourceType: 'module',
        ecmaVersion: nodeEcmaVersion,
        ecmaFeatures: {jsx: true},
      },
      env: {jest: true, node: true, browser: true},
    },
  ],
}
