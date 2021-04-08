module.exports = {
  setupFiles: ['<rootDir>/setupFiles.jest.js'],
  setupFilesAfterEnv: ['<rootDir>/setupFilesAfterEnv.jest.js'],
  moduleDirectories: ['node_modules', '<rootDir>/support_scripts/grunt/node_modules'],
  collectCoverageFrom: ['<rootDir>/public/**/*.js', '!**/build/**', '!<rootDir>/public/api/**', '!<rootDir>/public/**/*.min.js']
}
