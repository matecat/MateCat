module.exports = (api) => {
  const isTest = api.env('test')

  if (isTest) {
    return {
      presets: [
        '@babel/preset-react',
        ['@babel/preset-env', {targets: {node: 'current'}}],
      ],
    }
  }

  throw new Error('babel.config.js :: environment not supported, yet.')
}
