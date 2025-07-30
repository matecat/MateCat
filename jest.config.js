module.exports = {
  testEnvironment: 'jsdom',
  testEnvironmentOptions: {
    customExportConditions: [''],
  },
  setupFiles: ['<rootDir>/setupFiles.jest.js', '<rootDir>/jest.polyfills.js'],
  setupFilesAfterEnv: ['<rootDir>/setupFilesAfterEnv.jest.js'],
  collectCoverageFrom: [
    '<rootDir>/public/**/*.js',
    '!**/build/**',
    '!<rootDir>/public/api/**',
    '!<rootDir>/public/**/*.min.js',
  ],
  transformIgnorePatterns: ['node_modules/(?!@bundled-es-modules)/'],
  transform: {
    '^.+\\.js$': 'babel-jest',
    '.+\\.(css|styl|less|sass|scss)$': 'jest-transform-css',
  },
}
