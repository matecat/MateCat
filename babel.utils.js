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
      // The automatic runtime, as Vite builds the app: JSX imports
      // react/jsx-runtime, so a test that mocks `react` must keep the real module.
      ['@babel/preset-react', {runtime: 'automatic'}],
      ['@babel/preset-env', {targets: targetsMap[env]}],
    ],
    compact: true,
  }
}

exports.getBabelPresets = getBabelPresets
