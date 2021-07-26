const targetsMap = {
  node: {node: 'current'},
  browser: {browsers: ['defaults', 'not ie 11', 'not ie_mob 11']},
}

/**
 * Util to generate the babel presets configuration used
 * in the rest of the project.
 *
 * @param {'browser' | 'node'} env
 */
const getBabelPresets = (env = 'browser') => {
  return {
    presets: [
      '@babel/preset-react',
      ['@babel/preset-env', {targets: targetsMap[env]}],
    ],
  }
}

exports.getBabelPresets = getBabelPresets
