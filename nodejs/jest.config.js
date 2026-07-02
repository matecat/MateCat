module.exports = {
  testMatch: ['**/tests/**/*.test.js'],
  testEnvironment: 'node',
  coverageDirectory: './.coverage/',
  coverageReporters: ['clover', 'json', 'lcov', ['text', {skipFull: true}]],
  collectCoverageFrom: [
    '**/*.{js,jsx}',
    '!**/jest.config.js',
    '!**/node_modules/**',
    '!**/vendor/**',
    '!**/.coverage/**',
  ],
};
