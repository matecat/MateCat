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
      // Babel 8 defaults to the automatic runtime. Tests that mock `react`
      // with only `createElement` need JSX compiled to React.createElement.
      ['@babel/preset-react', {runtime: 'classic'}],
      ['@babel/preset-env', {targets: targetsMap[env]}],
    ],
    compact: true,
  }
}

exports.getBabelPresets = getBabelPresets
