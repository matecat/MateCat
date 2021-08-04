const {getBabelPresets} = require('./babel.utils')

module.exports = (api) => {
  switch (api.env()) {
    case 'test':
      return getBabelPresets('node')
    case 'development':
      return getBabelPresets('browser')

    default:
      throw new Error('babel.config.js :: environment not supported, yet.')
  }
}
