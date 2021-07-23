module.exports = (api) => {
  switch (api.env()) {
    case 'test':
      return {
        presets: [
          '@babel/preset-react',
          ['@babel/preset-env', {targets: {node: 'current'}}],
        ],
        plugins: ['@babel/plugin-transform-runtime'],
      }
    case 'development':
      return {
        presets: ['@babel/preset-react', ['@babel/preset-env']],
        plugins: ['@babel/plugin-transform-runtime'],
      }

    default:
      throw new Error('babel.config.js :: environment not supported, yet.')
  }
}
