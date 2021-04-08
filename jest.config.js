module.exports = {
  setupFiles: ['<rootDir>/setupFiles.jest.js'],
  setupFilesAfterEnv: ['<rootDir>/setupFilesAfterEnv.jest.js'],
  /**
   * At the moment we have some packages coming from the support_scripts/grunt/node_modules sub-folder
   * ideally we want to strip them out from there and remove this config option.
   */
  moduleDirectories: ['node_modules', '<rootDir>/support_scripts/grunt/node_modules'],
  collectCoverageFrom: ['<rootDir>/public/**/*.js', '!**/build/**', '!<rootDir>/public/api/**', '!<rootDir>/public/**/*.min.js']
}
