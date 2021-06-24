module.exports = {
  testEnvironment: 'jsdom',
  setupFiles: ['<rootDir>/setupFiles.jest.js'],
  setupFilesAfterEnv: ['<rootDir>/setupFilesAfterEnv.jest.js'],
  collectCoverageFrom: [
    '<rootDir>/public/**/*.js',
    '!**/build/**',
    '!<rootDir>/public/api/**',
    '!<rootDir>/public/**/*.min.js',
  ],
}
