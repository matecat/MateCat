module.exports = (api) => {
  switch (api.env()) {
    case 'test':
      return {
        presets: [
          '@babel/preset-react',
          ['@babel/preset-env', {targets: {node: 'current'}}],
        ],
      }
    case 'development':
      return {
        presets: ['@babel/preset-react', ['@babel/preset-env']],
      }

    default:
      throw new Error('babel.config.js :: environment not supported, yet.')
  }
}
