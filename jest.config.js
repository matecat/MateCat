module.exports = {
  testEnvironment: 'jsdom',
  testEnvironmentOptions: {
    customExportConditions: [''],
  },
  maxWorkers: '50%',
  workerIdleMemoryLimit: '256MB',
  forceExit: true,
  setupFiles: ['<rootDir>/setupFiles.jest.js', '<rootDir>/jest.polyfills.js'],
  setupFilesAfterEnv: ['<rootDir>/setupFilesAfterEnv.jest.js'],
  collectCoverageFrom: [
    '<rootDir>/public/**/*.js',
    '!**/build/**',
    '!<rootDir>/public/api/**',
    '!<rootDir>/public/**/*.min.js',
  ],
  roots: ['<rootDir>/public/'],
  testPathIgnorePatterns: ['/node_modules/'],
  transformIgnorePatterns: ['node_modules/(?!@bundled-es-modules)/', '.github/scripts/'],
  transform: {
    '^.+\\.js$': 'babel-jest',
    '.+\\.(css|styl|less|sass|scss)$': 'jest-transform-css',
  },
}
