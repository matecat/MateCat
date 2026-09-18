/**
 * Cattool-level settings a plugin can override.
 *
 * Plugins assign over these members (`catToolInterface.getCharacterCounterMode = ...`)
 * rather than patching a prototype, so core must read them through this object
 * at call time instead of capturing them.
 */
const catToolInterface = {
  getCharacterCounterMode() {},
}

export default catToolInterface
